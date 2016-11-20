<?php namespace Faddle\Http;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * HTTP Cookie 类
 */
class Cookie implements IteratorAggregate, ArrayAccess, Countable {

	/**
	 * The cookie
	 *
	 * @type array
	 */
	public $cookies = array();

	/**
	 * The date/time that the cookie should expire
	 *
	 * Represented by a Unix "Timestamp"
	 *
	 * @type int
	 */
	public $expire;

	/**
	 * The path on the server that the cookie will
	 * be available on
	 *
	 * @type string
	 */
	public $path;

	/**
	 * The domain that the cookie is available to
	 *
	 * @type string
	 */
	public $domain;

	/**
	 * Whether the cookie should only be transferred
	 * over an HTTPS connection or not
	 *
	 * @type boolean
	 */
	public $secure = false;

	public $httponly = false;

	/**
	 * Constructor
	 *
	 * @param string  $name         The name of the cookie
	 * @param string  $value        The value to set the cookie with
	 * @param int     $expire       The time that the cookie should expire
	 * @param string  $path         The path of which to restrict the cookie
	 * @param string  $domain       The domain of which to restrict the cookie
	 * @param boolean $secure       Flag of whether the cookie should only be sent over a HTTPS connection
	 */
	public function __construct(
		$cookies = null,
		$expire = 0,
		$path = '/',
		$domain = '',
		$secure = false,
		$http_only = false
	) {
		// Initialize our properties
		if (! isset($cookies)) $cookies = $_COOKIE;
		$this->set($cookies);
		$this->setExpire($expire);
		$this->setPath($path);
		$this->setDomain($domain);
		$this->setSecure($secure);
	}

	/**
	 * Gets the cookie
	 *
	 * @return string
	 */
	public function get($name, $default=null) {
		if (isset($this->cookies[$name])) {
			$value = $this->cookies[$name];
			$expires = intval($this->getExpire());
			if (is_array($value)) {
				if (array_key_exists('expires', $value)) {
					$expires = intval($value['expires']);
				}
				if (array_key_exists('value', $value)) $value = $value['value'];
				//else $value = $value[0];
			}
			if ($expires > time() || $expires === 0) {
				return $value;
			} else {
				//unset($this->cookies[$name]);
				return $default;
			}
		}
		return $default;
	}

	/**
	 * Sets the cookie
	 *
	 * @param string $value
	 * @return Cookie
	 */
	public function set($name, $value=null, $expires=0) {
		if (is_array($name)) {
			foreach ($name as $k => $v) {
				$this->set($k, $v);
			}
			return $this;
		}
		
		$name = (string) $name;
		if (! is_array($value) && null !== $value) {
			$value = (string) $value;
		} else {
			$value = $value;
		}
		if ($expires) {
			$value = array (
				'value' => $value,
				'expires' => time() + intval($expires)
			);
		}
		
		$this->cookies[$name] = $value;
		
		return $this;
	}

	public function cookies(array $cookies=null) {
		if (!empty($cookies)) {
			foreach ((array) $cookies as $name => $value) {
				$this->set($name, $value);
			}
		}
		return $this->cookies;
	}

	public function exists($name) {
		return isset($this->cookies[$name]);
	}

	public function remove($name) {
		$value = $this->get($name);
		unset($this->cookies[$name]);
		@setcookie($name, null, time() - (3600*24*365)
				, $this->getPath()
				, $this->getDomain()
				, $this->getSecure());
		return $value;
	}

	/**
	 * 删除多个 Cookie 方法
	 */
	public function delete($key) {
		$args = func_get_args();
		foreach ($args as $key) {
			unset($this->cookies[$key]);
		}
		return $this;
	}

	public function clear() {
		foreach ($this->cookies as $key => $value) {
			$this->remove($key);
		}
		return $this;
	}

	protected function _hash($data, $secret=null, $algo='sha512') {
		if (is_null($secret))  $secret = md5(php_uname() . getmypid());
		return hash_hmac($algo, $data, $secret);
	}

	private function _encode($value) {
		return base64_encode(serialize($value));
	}
	
	private function _decode($value) {
		return base64_decode(unserialize($value));
	}

	public function send() {
		foreach ($this->cookies as $name => $value) {
			//$value = $this->get($name);
			$expires = intval($this->getExpire());
			if (is_array($value)) {
				if (array_key_exists('expires', $value)) {
					$expires = intval($value['expires']);
				}
				if (array_key_exists('value', $value)) $value = $value['value'];
			}
			@setcookie($name, $value, $expires 
				//, $this->getExpire()
				, $this->getPath()
				, $this->getDomain()
				, $this->getSecure()
				);
			unset($this->cookies[$name]);
		}
	}

	/**
	 * Gets the cookie's expire time
	 *
	 * @return int
	 */
	public function getExpire() {
		return $this->expire;
	}

	/**
	 * Sets the cookie's expire time
	 *
	 * The time should be an integer
	 * representing a Unix timestamp
	 *
	 * @param int $expire
	 * @return ResponseCookie
	 */
	public function setExpire($expire) {
		if (null !== $expire) {
			$this->expire = (int) $expire;
		} else {
			$this->expire = $expire;
		}
		
		return $this;
	}

	/**
	 * Gets the cookie's path
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Sets the cookie's path
	 *
	 * @param string $path
	 * @return ResponseCookie
	 */
	public function setPath($path) {
		if (null !== $path) {
			$this->path = (string) $path;
		} else {
			$this->path = $path;
		}
		
		return $this;
	}

	/**
	 * Gets the cookie's domain
	 *
	 * @return string
	 */
	public function getDomain() {
		return $this->domain;
	}

	/**
	 * Sets the cookie's domain
	 *
	 * @param string $domain
	 * @return ResponseCookie
	 */
	public function setDomain($domain) {
		if (null !== $domain) {
			$this->domain = (string) $domain;
		} else {
			$this->domain = $domain;
		}
		
		return $this;
	}

	/**
	 * Gets the cookie's secure only flag
	 *
	 * @return boolean
	 */
	public function getSecure() {
		return $this->secure;
	}

	/**
	 * Sets the cookie's secure only flag
	 *
	 * @param boolean $secure
	 * @return ResponseCookie
	 */
	public function setSecure($secure) {
		$this->secure = (boolean) $secure;
		return $this;
	}


	/* 实现魔法函数 */
	
	/** {@inheritdoc} */
	public function __get($key) {
		return $this->get($key);
	}
	/** {@inheritdoc} */
	public function __set($key, $value) {
		$this->set($key, $value);
	}
	/** {@inheritdoc} */
	public function __isset($key) {
		return $this->exists($key);
	}
	/** {@inheritdoc} */
	public function __unset($key) {
		$this->remove($key);
	}
	/** {@inheritdoc} */
	public function getIterator() {
		return new ArrayIterator($this->cookies);
	}
	/** {@inheritdoc} */
	public function offsetGet($key) {
		return $this->get($key);
	}
	/** {@inheritdoc} */
	public function offsetSet($key, $value) {
		$this->set($key, $value);
	}
	/** {@inheritdoc} */
	public function offsetExists($key) {
		return $this->exists($key);
	}
	/** {@inheritdoc} */
	public function offsetUnset($key) {
		$this->remove($key);
	}
	/** {@inheritdoc} */
	public function count() {
		return count($this->cookies);
	}


	/* 以下为过时函数，将在下一版本舍弃使用。 */
	
	static $_cookies = array();
	
	public static function set_cookie($key, $value, $expires=null, $domain='-', $path='/') {
		$domain = strtolower($domain);
		if (substr($domain, 0, 1) === '.') {
			$domain = substr($domain, 1);
		}
		if (!isset(static::$_cookies[$domain])) {
			static::$_cookies[$domain] = [];
		}
		if (!isset(static::$_cookies[$domain][$path])) {
			static::$_cookies[$domain][$path] = [];
		}
		$list = &static::$_cookies[$domain][$path];
		if ($value === null || $value === '' || ($expires !== null && $expires < time())) {
			unset($list[$key]);
		} else {
			$value = rawurlencode($value);
			$list[$key] = ['value' => $value, 'expires' => $expires];
		}
	}
	
	public static function clear_cookie($domain='-', $path=null) {
		if ($domain === null) {
			static::$_cookies = [];
		} else {
			$domain = strtolower($domain);
			if ($path === null) {
				unset(static::$_cookies[$domain]);
			} else {
				if (isset(static::$_cookies[$domain])) {
					unset(static::$_cookies[$domain][$path]);
				}
			}
		}
	}
	
	public static function get_cookie($key, $domain='-') {
		$domain = strtolower($domain);
		if ($key === null) {
			$cookies = [];
		}
		while (true) {
			if (isset(static::$_cookies[$domain])) {
				foreach (static::$_cookies[$domain] as $path => $list) {
					if ($key === null) {
						$cookies = array_merge($list, $cookies);
					} else {
						if (isset($list[$key])) {
							return rawurldecode($list[$key]['value']);
						}
					}
				}
			}
			if (($pos = strpos($domain, '.', 1)) === false) {
				break;
			}
			$domain = substr($domain, $pos);
		}
		return $key === null ? $cookies : null;
	}
	
	public static function get_cookies($host, $path) {
		$now = time();
		$host = strtolower($host);
		$cookies = [];
		$domains = ['-', $host];
		while (strlen($host) > 1 && ($pos = strpos($host, '.', 1)) !== false) {
			$host = substr($host, $pos + 1);
			$domains[] = $host;
		}
		foreach ($domains as $domain) {
			if (!isset(static::$_cookies[$domain])) {
				continue;
			}
			foreach (static::$_cookies[$domain] as $_path => $list) {
				if (!strncmp($_path, $path, strlen($_path))
					&& (substr($_path, -1, 1) === '/' || substr($path, strlen($_path), 1) === '/')
				) {
					foreach ($list as $k => $v) {
						if (!isset($cookies[$k]) && ($v['expires'] === null || $v['expires'] > $now)) {
							$cookies[$k] = $k . '=' . $v['value'];
						}
					}
				}
			}
		}
		
		return $cookies;
	}

}
