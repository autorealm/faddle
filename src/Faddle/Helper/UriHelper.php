<?php namespace Faddle\Helper;


class UriHelper {

	private $parts = array();

	public function __construct($uri = null) {
		if (empty($uri)) {
			return;
		}
		
		if (is_string($uri)) {
			$this->parts = parse_url($uri);
			if (!empty($this->parts['query'])) {
				$query = array();
				parse_str($this->parts['query'], $query);
				$this->parts['query'] = $query;
			}
			
			return;
		}
		
		if ($uri instanceof self) {
			$this->parts = $uri->parts;
			
			return;
		}
		
		if (is_array($uri)) {
			$this->parts = $uri;
			
			return;
		}
	}

	public static function __callStatic($func, $args) {
		if (method_exists(Uri::class, $func)) {
			//$args = array_slice(func_get_args(), 1);
			$func = array(Uri::class, $func);
			return call_user_func_array($func, $args);
		}
	}

	public function __toString() {
		return $this->build();
	}

	public function __unset($name) {
		unset($this->parts[$name]);
	}

	public function __set($name, $value) {
		$this->parts[$name] = $value;
	}

	public function __get($name) {
		return $this->parts[$name];
	}

	public function __isset($name) {
		return isset($this->parts[$name]);
	}

	public function build() {
		$uri = '';
		$parts = $this->parts;
		
		if (!empty($parts['scheme'])) {
			$uri .= $parts['scheme'] . ':';
			if (!empty($parts['host'])) {
				$uri .= '//';
				if (!empty($parts['user'])) {
					$uri .= $parts['user'];
					
					if (!empty($parts['pass'])) {
						$uri .= ':' . $parts['pass'];
					}
					
					$uri .= '@';
				}
				$uri .= $parts['host'];
			}
		}
		
		if (!empty($parts['port'])) {
			$uri .= ':' . $parts['port'];
		}
		
		if (!empty($parts['path'])) {
			$uri .= $parts['path'];
		}
		
		if (!empty($parts['query'])) {
			$uri .= '?' . $this->buildQuery($parts['query']);
		}
		
		if (!empty($parts['fragment'])) {
			$uri .= '#' . $parts['fragment'];
		}
		
		return $uri;
	}

	public function buildQuery($query) {
		return (is_array($query) ? http_build_query($query) : $query);
	}

	public function resolve($uri) {
		$newUri = new self($this);
		$newUri->extend($uri);
		
		return $newUri;
	}

	public function extend($uri) {
		if (!$uri instanceof self) {
			$uri = new self($uri);
		}
		
		$this->parts = array_merge($this->parts, array_diff_key($uri->parts, array_flip(array(
				'query',
				'path' 
		))));
		
		if (!empty($uri->parts['query'])) {
			$this->extendQuery($uri->parts['query']);
		}
		
		if (!empty($uri->parts['path'])) {
			$this->extendPath($uri->parts['path']);
		}
		
		return $this;
	}

	public function extendQuery($params) {
		$query = empty($this->parts['query']) ? array() : $this->parts['query'];
		$params = empty($params) ? array() : $params;
		$this->parts['query'] = array_merge($query, $params);
		
		return $this;
	}

	public function extendPath($path) {
		if (empty($path)) {
			return $this;
		}
		
		if (!strncmp($path, '/', 1)) {
			$this->parts['path'] = $path;
			
			return $this;
		}
		
		if (empty($this->parts['path'])) {
			$this->parts['path'] = '/' . $path;
			
			return $this;
		}
		
		$this->parts['path'] = substr($this->parts['path'], 0, strrpos($this->parts['path'], '/') + 1) . $path;
		
		return $this;
	}


}



final class Uri {

	/**
	 * Resolves relative urls, like a browser would.
	 *
	 * This function takes a basePath, which itself _may_ also be relative, and
	 * then applies the relative path on top of it.
	 *
	 * @param string $basePath
	 * @param string $newPath
	 * @return string
	 */
	public static function resolve($basePath, $newPath) {
		
		$base = static::parse($basePath);
		$delta = static::parse($newPath);
		
		$pick = function($part) use ($base, $delta) {
		
			if ($delta[$part]) {
				return $delta[$part];
			} elseif ($base[$part]) {
				return $base[$part];
			}
			return null;
			
		};
		
		// If the new path defines a scheme, it's absolute and we can just return
		// that.
		if ($delta['scheme']) {
			return static::build($delta);
		}
		
		$newParts = [];
		
		$newParts['scheme'] = $pick('scheme');
		$newParts['host']   = $pick('host');
		$newParts['port']   = $pick('port');
		
		$path = '';
		if ($delta['path']) {
			// If the path starts with a slash
			if ($delta['path'][0] === '/') {
				$path = $delta['path'];
			} else {
				// Removing last component from base path.
				$path = $base['path'];
				if (strpos($path, '/') !== false) {
					$path = substr($path, 0, strrpos($path, '/'));
				}
				$path .= '/' . $delta['path'];
			}
		} else {
			$path = $base['path'] ?: '/';
		}
		// Removing .. and .
		$pathParts = explode('/', $path);
		$newPathParts = [];
		foreach ($pathParts as $pathPart) {
			switch ($pathPart) {
				//case '' :
				case '.' :
					break;
				case '..' :
					array_pop($newPathParts);
					break;
				default :
					$newPathParts[] = $pathPart;
					break;
			}
		}
		
		$path = implode('/', $newPathParts);
		
		// If the source url ended with a /, we want to preserve that.
		$newParts['path'] = $path;
		if ($delta['query']) {
			$newParts['query'] = $delta['query'];
		} elseif (!empty($base['query']) && empty($delta['host']) && empty($delta['path'])) {
			// Keep the old query if host and path didn't change
			$newParts['query'] = $base['query'];
		}
		if ($delta['fragment']) {
			$newParts['fragment'] = $delta['fragment'];
		}
		return static::build($newParts);
	}

	/**
	 * Takes a URI or partial URI as its argument, and normalizes it.
	 *
	 * After normalizing a URI, you can safely compare it to other URIs.
	 * This function will for instance convert a %7E into a tilde, according to
	 * rfc3986.
	 *
	 * It will also change a %3a into a %3A.
	 *
	 * @param string $uri
	 * @return string
	 */
	public static function normalize($uri) {
		$parts = static::parse($uri);
		
		if (!empty($parts['path'])) {
			$pathParts = explode('/', ltrim($parts['path'], '/'));
			$newPathParts = [];
			foreach ($pathParts as $pathPart) {
				switch ($pathPart) {
					case '.':
						// skip
						break;
					case '..' :
						// One level up in the hierarchy
						array_pop($newPathParts);
						break;
					default :
						// Ensuring that everything is correctly percent-encoded.
						$newPathParts[] = rawurlencode(rawurldecode($pathPart));
						break;
				}
			}
			$parts['path'] = '/' . implode('/', $newPathParts);
		}
		
		if ($parts['scheme']) {
			$parts['scheme'] = strtolower($parts['scheme']);
			$defaultPorts = [
				'http'  => '80',
				'https' => '443',
			];
			
			if (!empty($parts['port']) && isset($defaultPorts[$parts['scheme']]) && $defaultPorts[$parts['scheme']] == $parts['port']) {
				// Removing default ports.
				unset($parts['port']);
			}
			// A few HTTP specific rules.
			switch ($parts['scheme']) {
				case 'http' :
				case 'https' :
					if (empty($parts['path'])) {
						// An empty path is equivalent to / in http.
						$parts['path'] = '/';
					}
					break;
			}
		}
		
		if ($parts['host']) $parts['host'] = strtolower($parts['host']);
		
		return static::build($parts);
	}

	/**
	 * Parses a URI and returns its individual components.
	 *
	 * This method largely behaves the same as PHP's parse_url, except that it will
	 * return an array with all the array keys, including the ones that are not
	 * set by parse_url, which makes it a bit easier to work with.
	 *
	 * @param string $uri
	 * @return array
	 */
	public static function parse($uri) {
		return
			parse_url($uri) + [
				'scheme'   => null,
				'host'     => null,
				'path'     => null,
				'port'     => null,
				'user'     => null,
				'query'    => null,
				'fragment' => null,
			];
	}

	/**
	 * This function takes the components returned from PHP's parse_url, and uses
	 * it to generate a new uri.
	 *
	 * @param array $parts
	 * @return string
	 */
	public static function build(array $parts) {
		$uri = '';
		$authority = '';
		
		if (!empty($parts['host'])) {
			$authority = $parts['host'];
			if (!empty($parts['user'])) {
				$authority = $parts['user'] . '@' . $authority;
			}
			if (!empty($parts['port'])) {
				$authority = $authority . ':' . $parts['port'];
			}
		}
		
		if (!empty($parts['scheme'])) {
			// If there's a scheme, there's also a host.
			$uri = $parts['scheme'] . ':';
		}
		if ($authority) {
			// No scheme, but there is a host.
			$uri .= '//' . $authority;
		}
		
		if (!empty($parts['path'])) {
			$uri .= $parts['path'];
		}
		if (!empty($parts['query'])) {
			$uri .= '?' . $parts['query'];
		}
		if (!empty($parts['fragment'])) {
			$uri .= '#' . $parts['fragment'];
		}
		
		return $uri;
	}

	/**
	 * Returns the 'dirname' and 'basename' for a path.
	 *
	 * The reason there is a custom function for this purpose, is because
	 * basename() is locale aware (behaviour changes if C locale or a UTF-8 locale
	 * is used) and we need a method that just operates on UTF-8 characters.
	 *
	 * In addition basename and dirname are platform aware, and will treat
	 * backslash (\) as a directory separator on windows.
	 *
	 * This method returns the 2 components as an array.
	 *
	 * If there is no dirname, it will return an empty string. Any / appearing at
	 * the end of the string is stripped off.
	 *
	 * @param string $path
	 * @return array
	 */
	public static function split($path) {
		$matches = [];
		if (preg_match('/^(?:(?:(.*)(?:\/+))?([^\/]+))(?:\/?)$/u', $path, $matches)) {
			return [$matches[1], $matches[2]];
		}
		return [null,null];
		
	}

}