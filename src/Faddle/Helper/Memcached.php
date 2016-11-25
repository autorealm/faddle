<?php namespace Faddle\Helper;

/**
 * Memcached Wrapper class
 */
class Memcached {

	/**
	 * @var \Memcached $cache
	 */
	private static $cache = null;
	private $prefix = '';

	public function __construct($prefix = '', $memcached_server=false) {
		if (is_null(self::$cache)) {
			self::$cache = new \Memcached();
			if ($memcached_server) {
				$servers = array((array) $memcached_server);
			} else {
				$servers = array(array('localhost', 11211));
			}
			self::$cache->addServers($servers);
		}
		$this->prefix = $prefix;
	}

	protected function getPrefix() {
		return $this->prefix;
	}

	public function get($key) {
		$result = self::$cache->get($this->getPrefix() . $key);
		if ($result === false and self::$cache->getResultCode() == \Memcached::RES_NOTFOUND) {
			return null;
		} else {
			return $result;
		}
	}

	public function set($key, $value, $ttl = 0) {
		if ($ttl > 0) {
			return self::$cache->set($this->getPrefix() . $key, $value, $ttl);
		} else {
			return self::$cache->set($this->getPrefix() . $key, $value);
		}
	}

	public function has($key) {
		self::$cache->get($this->getPrefix() . $key);
		return self::$cache->getResultCode() === \Memcached::RES_SUCCESS;
	}

	public function remove($key) {
		return self::$cache->delete($this->getPrefix() . $key);
	}

	public function clear($prefix = '') {
		$prefix = $this->getPrefix() . $prefix;
		$allKeys = self::$cache->getAllKeys();
		if ($allKeys === false) {
			// newer Memcached doesn't like getAllKeys(), flush everything
			self::$cache->flush();
			return true;
		}
		$keys = array();
		$prefixLength = strlen($prefix);
		foreach ($allKeys as $key) {
			if (substr($key, 0, $prefixLength) === $prefix) {
				$keys[] = $key;
			}
		}
		if (method_exists(self::$cache, 'deleteMulti')) {
			self::$cache->deleteMulti($keys);
		} else {
			foreach ($keys as $key) {
				self::$cache->delete($key);
			}
		}
		return true;
	}

	/**
	 * Set a value in the cache if it's not already stored
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl Time To Live in seconds. Defaults to 60*60*24
	 * @return bool
	 */
	public function add($key, $value, $ttl = 0) {
		return self::$cache->add($this->getPrefix() . $key, $value, $ttl);
	}

	/**
	 * Increase a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 */
	public function inc($key, $step = 1) {
		$this->add($key, 0);
		return self::$cache->increment($this->getPrefix() . $key, $step);
	}

	/**
	 * Decrease a stored number
	 *
	 * @param string $key
	 * @param int $step
	 * @return int | bool
	 */
	public function dec($key, $step = 1) {
		return self::$cache->decrement($this->getPrefix() . $key, $step);
	}

	static public function isAvailable() {
		return extension_loaded('memcached');
	}
	
	/**
	 * Compare and set
	 *
	 * @param string $key
	 * @param mixed $old
	 * @param mixed $new
	 * @return bool
	 */
	public function cas($key, $old, $new) {
		//no native cas, emulate with locking
		if ($this->add($key . '_lock', true)) {
			if ($this->get($key) === $old) {
				$this->set($key, $new);
				$this->remove($key . '_lock');
				return true;
			} else {
				$this->remove($key . '_lock');
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Compare and delete
	 *
	 * @param string $key
	 * @param mixed $old
	 * @return bool
	 */
	public function cad($key, $old) {
		//no native cas, emulate with locking
		if ($this->add($key . '_lock', true)) {
			if ($this->get($key) === $old) {
				$this->remove($key);
				$this->remove($key . '_lock');
				return true;
			} else {
				$this->remove($key . '_lock');
				return false;
			}
		} else {
			return false;
		}
	}
}
