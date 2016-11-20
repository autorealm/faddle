<?php namespace Faddle\Http;

use InvalidArgumentException;
use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * HTTP 头信息类
 */
class Header implements IteratorAggregate, ArrayAccess, Countable {

	/** 存储的 Header 数据 */
	protected $headers = array();
	
	/**
	 * Constructor
	 *
	 * @param array $headers        The headers of this collection
	 */
	public function __construct(array $headers=array()) {
		foreach ($headers as $key => $value) {
			$this->set($key, $value);
		}
	}

	/**
	 * Get a header
	 *
	 * @param string $key           The key of the header to return
	 * @param mixed  $default_val   The default value of the header if it contains no value
	 * @return mixed
	 */
	public function get($key, $default_val = null) {
		$key = $this->normalizeKey($key);
		if (isset($this->headers[$key])) {
			$value = $this->headers[$key];
			//$value = implode(',', $value);
			if (is_array($value) and count($value) == 1) return $value[0];
			else return $value;
		}
		
		return $default_val;
	}

	/**
	 * Set a header
	 *
	 * @param string $key   The key of the header to set
	 * @param mixed  $value The value of the header to set
	 * @return HeaderDataCollection
	 */
	public function set($key, $value) {
		$key = $this->normalizeKey($key);
		$this->headers[$key] = (array) $value;
		return $this;
	}

	/**
	 * Check if a header exists
	 *
	 * @param string $key   The key of the header
	 * @return boolean
	 */
	public function exists($key) {
		$key = $this->normalizeKey($key);
		return array_key_exists($key, $this->headers);
	}

	/**
	 * Remove a header
	 *
	 * @param string $key   The key of the header
	 * @return void
	 */
	public function remove($key) {
		$key = $this->normalizeKey($key);
		$value = $this->headers[$key];
		unset($this->headers[$key]);
		return $value;
	}

	/**
	 * Normalize a header key based on our set normalization style
	 *
	 * @param string $key The ("field") key of the header
	 * @return string
	 */
	protected function normalizeKey($key) {
		$key = trim((string) $key);
		$key = str_replace(array(' ', '_'), '-', $key);
		$key = strtolower($key);
		$words = explode('-', strtolower($key));
		foreach ($words as &$word) {
			$word = ucfirst($word);
		}
		$key = implode('-', $words);
		return $key;
	}

	private function filterKey($key) {
		$filtered = str_replace('-', ' ', $key);
		$filtered = ucwords($filtered);
		return str_replace(' ', '-', $filtered);
	}

	public function all() {
		return $this->headers;
	}

	public function send() {
		foreach ($this->headers as $header => $values) {
			$name  = $this->filterKey($header);
			$first = true;
			foreach ((array) $values as $value) {
				@header(sprintf('%s: %s', $name, $value), $first);
				$first = false;
			}
			unset($this->headers[$header]);
		}
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
		return new ArrayIterator($this->headers);
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
		return count($this->headers);
	}

	/**
	 * 解析 Header 内容
	 */
	public static function parse($content) {
		if (empty($content)) return false;
		
		if (is_string($content)) {
			$content = array_filter(explode("\r\n", $content));
		} elseif (!is_array($content)) {
			return false;
		}
		$headers = array();
		$status = array();
		if (preg_match('/^HTTP\/(\d(?:\.\d)?)\s+(\d{3})\s+(.+)$/i', $content[0], $status)) {
			array_shift($content);
			$version = $status[1];
			$statusCode = intval($status[2]);
			$statusMessage = $status[3];
		}
		foreach ($content as $field) {
			if (!is_array($field)) {
				$field = array_map('trim', explode(':', $field, 2));
			}
			if (count($field) == 2) {
				$headers[$field[0]] = $field[1];
			}
		}
		
		return [$headers, $version, $statusCode, $statusMessage];
	}

	/**
	 * 处理缓存的HTTP头信息
	 */
	public static function process_cache_headers($etag=null, $last_modified=null, $max_age=10, $check_change=true) {
		
		if (empty($last_modified) && empty($etag)) {
			return;
		}
		if (\headers_sent($file, $line)) {
			return true;
		}
		
		header("Pragma: public");
		header("Cache-Control: public, maxage=$max_age, must-revalidate");
		
		if (function_exists('header_remove')) {
			header_remove("Expires");
		} else {
			header('Expires: ');
		}
		if (!empty($last_modified)) {
			header("Last-Modified: $last_modified");
		}
		if (!empty($etag)) {
			header("Etag: $etag");
		}
		if (!$check_change) {
			return true;
		}
		
		$no_change_by_etag = $no_change_by_timestamp = false;
		
		if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && (null !== $last_modified)) {
			if (false === $no_change_by_timestamp = self::detect_nochange_by_timestamp($last_modified)) {
				return true;
			}
		}
		if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && (null !== $etag)) {
			if (false === $no_change_by_etag = self::is_etag_match($etag)) {
				return true;
			}
		}
		if ($no_change_by_etag || $no_change_by_timestamp) {
			if ($no_change_by_etag && ('GET' !== $_SERVER['REQUEST_METHOD'] && 'HEAD' !== $_SERVER['REQUEST_METHOD'])) {
				header("HTTP/1.1 412 (Precondition Failed)");
				throw new \OutOfBoundsException;
			}
			header("HTTP/1.1 304 Not Modified");
			throw new \OutOfBoundsException;
		}
		
		return true;
	}
	
	protected static function is_etag_match($etag = null) {
		if ('*' === $_SERVER['HTTP_IF_NONE_MATCH']) {
			return true;
		}
		if (!strstr($etag, ', ')) {
			return ($etag === $_SERVER['HTTP_IF_NONE_MATCH']);
		}
		$etags = explode(',', $_SERVER['HTTP_IF_NONE_MATCH']);
		
		foreach ($etags as $tag) {
			if (trim($tag) === $etag) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * 
	 * @return bool true means definite 'no change', false
	 * means content has changed
	 */
	protected static function detect_nochange_by_timestamp($last_modified) {
		if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] === $last_modified) {
			return true;
		}
		if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= strtotime($last_modified)) {
			return true;
		}
		
		return false;
	}

	/**
	 * Filter a header value
	 *
	 * Ensures CRLF header injection vectors are filtered.
	 *
	 * Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
	 * tabs are allowed in values; header continuations MUST consist of
	 * a single CRLF sequence followed by a space or horizontal tab.
	 *
	 * This method filters any values not allowed from the string, and is
	 * lossy.
	 *
	 * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
	 * @param string $value
	 * @return string
	 */
	public static function filter($value) {
		$value  = (string) $value;
		$length = strlen($value);
		$string = '';
		for ($i = 0; $i < $length; $i += 1) {
			$ascii = ord($value[$i]);
			// Detect continuation sequences
			if ($ascii === 13) {
				$lf = ord($value[$i + 1]);
				$ws = ord($value[$i + 2]);
				if ($lf === 10 && in_array($ws, [9, 32], true)) {
					$string .= $value[$i] . $value[$i + 1];
					$i += 1;
				}
				continue;
			}
			// Non-visible, non-whitespace characters
			// 9 === horizontal tab
			// 32-126, 128-254 === visible
			// 127 === DEL
			// 255 === null byte
			if (($ascii < 32 && $ascii !== 9)
				|| $ascii === 127
				|| $ascii > 254
			) {
				continue;
			}
			$string .= $value[$i];
		}
		
		return $string;
	}

	/**
	 * Validate a header.
	 *
	 * Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
	 * tabs are allowed in values; header continuations MUST consist of
	 * a single CRLF sequence followed by a space or horizontal tab.
	 *
	 * @see http://en.wikipedia.org/wiki/HTTP_response_splitting
	 * @param string|array $name
	 * @param string|array $value
	 * @return bool
	 */
	public static function isValid($name, $value=null) {
		if (! is_null($name)) {
			if (is_array($name)) {
				foreach ($name as $k => $v) {
					if (static::isValid($k, $v)) continue;
					else return false;
				}
				return true;
			}
			$name  = (string) $name;
			if (! preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
				return false;
			}
		}
		if (!isset($value) || is_null($value)) return true;
		if (is_array($value)) {
			foreach ($value as $v) {
				if (static::isValid(null, $v)) continue;
				else return false;
			}
			return true;
		}
		$value  = (string) $value;
		// Look for:
		// \n not preceded by \r, OR
		// \r not followed by \n, OR
		// \r\n not followed by space or horizontal tab; these are all CRLF attacks
		if (preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $value)) {
			return false;
		}
		$length = strlen($value);
		for ($i = 0; $i < $length; $i += 1) {
			$ascii = ord($value[$i]);
			// Non-visible, non-whitespace characters
			// 9 === horizontal tab
			// 10 === line feed
			// 13 === carriage return
			// 32-126, 128-254 === visible
			// 127 === DEL
			// 255 === null byte
			if (($ascii < 32 && ! in_array($ascii, [9, 10, 13], true))
				|| $ascii === 127
				|| $ascii > 254
			) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Assert a header value is valid.
	 *
	 * @param string $value
	 * @throws InvalidArgumentException for invalid values
	 */
	public static function assertValidValue($value) {
		if (! self::isValid(null, $value)) {
			throw new InvalidArgumentException('Invalid header value');
		}
	}

	/**
	 * Assert whether or not a header name is valid.
	 *
	 * @see http://tools.ietf.org/html/rfc7230#section-3.2
	 * @param mixed $name
	 * @throws InvalidArgumentException
	 */
	public static function assertValidName($name) {
		if (! self::isValid($name, null)) {
			throw new InvalidArgumentException('Invalid header name');
		}
	}

}