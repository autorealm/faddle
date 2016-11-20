<?php namespace Faddle\Storage;

/**
 * 静态缓存类
 * 
 * @author KYO
 * @since 2015-09-15
 */
class StaticCache {

	/**
	 * 缓存文件目录
	 */
	private $_cachepath = '/temp/static_caches/';

	/**
	 * 缓存文件扩展名
	 */
	private $_extension = '.cache';
	private $_prefix = '';

	private $_data = null;


	/**
	 * 默认构造器
	 * @param string 缓存路径
	 * @param string 缓存扩展名
	 */
	public function __construct($cachedir, $cacheext='', $prefix='') {
		if (isset($cachedir)) {
			$this->_cachepath = trim($cachedir);
		}
		if (isset($cachedir)) {
			$this->_extension = trim($cacheext);
		}
		if (isset($prefix)) {
			$this->_prefix = trim($prefix);
		}
	}

	/**
	 * 是否存在指定缓存
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function isCached($key) {
		$cachedData = $this->_loadCache($key);
		if (false != $cachedData) {
			return isset($cachedData['data']);
		}
		return false;
	}

	/**
	 * 存储缓存
	 *
	 * @param string $key 键
	 * @param mixed $data 值
	 * @param integer [optional] $expiration 过期时间（秒）
	 * @return object
	 */
	public function store($key, $data, $expiration=0) {
		$cache_file_path = $this->getCachePath($key);
		if (! $cache_file_path) return false;
		$storeData = array(
			'key'	 => $key,
			'time'	 => time(),
			'expire' => $expiration,
			'data'	 => serialize($data)
		);
		$content = "<?php\nreturn " . var_export($storeData, true) . ";";
		file_put_contents($cache_file_path, $content, LOCK_EX);
		return $this;
	}

	/**
	 * 还原缓存
	 * 
	 * @param string $key
	 * @return string
	 */
	public function retrieve($key, $remove=false) {
		$cached = $this->_loadCache($key);
		if (! $cached or ! is_array($cached)) return false;
		if (true === $this->_checkExpired($cached['time'], $cached['expire'])) {
			if ($remove) $this->remove($key);
			return false;
		}
		return unserialize($cached['data']);
	}

	/**
	 * 缓存方法，当指定 key 未缓存时调用 callback 取得 data 并缓存再返回。
	 * @param string $key 键
	 * @param mixed $callback 回调值
	 * @param integer [optional] $expiration 过期时间（秒）
	 * @return object
	 */
	public function cache($key, $callback, $expiration=0) {
		if ($data = $this->retrieve($key)) {
			return $data;
		} else {
			$data = false;
			if (is_callable($callback)) {
				$data = call_user_func($callback);
			} else {
				$callback = (string) $callback; 
				$calls = explode('@', $callback);
				$method = $calls[1];
				if (class_exists($calls[0])) $callback = new $calls[0]();
				else $callback = $calls[0];
				if (method_exists($callback, $method)) {
					$data = call_user_func(array($callback, $method));
				} else if (function_exists($callback)) {
					$data = call_user_func($callback);
				}
			}
			if ($data) $this->store($key, $data, $expiration);
			return $data;
		}
		
	}

	/**
	 * 读取缓存信息
	 * 
	 * @return array
	 */
	private function getInfo($key) {
		$filepath = $this->getCachePath($key);
		if (true === file_exists($filepath)) {
			$cached = include_once($filepath);
			$info['key'] = $key;
			$info['time'] = $cached['time'];
			$info['expire'] = $cached['expire'];
			$info['data'] = $cached['data'];
			$info['name'] = basename($filepath);
			$info['path'] = rtrim($this->_cachepath, '/');
			$info['ctime'] = filectime($filepath);
			$info['mtime'] = filemtime($filepath);
			$info['size'] = filesize($filepath);
			return $info;
		} else {
			return false;
		}
	}

	/**
	 * 移除指定缓存
	 * 
	 * @param string $key
	 * @return boolean
	 */
	public function remove($key) {
		$filepath = $this->getCachePath($key);
		if (true === file_exists($filepath)) {
			return @unlink($filepath);
		} else {
			return true;
		}
	}

	/**
	 * 清空所有缓存
	 * 
	 * @return object
	 */
	public function clear() {
		if (true === $this->_checkCacheDir()) {
			$this->_cachepath = rtrim($this->_cachepath, '/') . '/';
			$path = glob($this->_cachepath . $this->_prefix . '*' . $this->_extension);
			foreach ((array) $path as $file) {
				if (!in_array(basename($file), array('.', '..'))) @unlink($file);
			}
		}
		return $this;
	}

	/**
	 * 读取缓存
	 * 
	 * @return mixed
	 */
	private function _loadCache($key) {
		$filepath = $this->getCachePath($key);
		if (true === file_exists($filepath)) {
			$cache = include_once($filepath);
			return $cache;
		} else {
			return false;
		}
	}

	/**
	 * 获取缓存文件完整路径
	 * 
	 * @return string
	 */
	public function getCachePath($filename) {
		if (true === $this->_checkCacheDir()) {
			$filename = preg_replace('/[^0-9a-z\.\_\-]/i', '', strtolower($filename));
			$this->_cachepath = rtrim($this->_cachepath, '/') . '/';
			return $this->_cachepath . $this->_getHash($filename) . $this->_extension;
		}
		return $filename;
	}

	/**
	 * Get the filename hash
	 * 
	 * @return string
	 */
	private function _getHash($filename) {
		return $this->_prefix . sha1($filename);
	}

	/**
	 * 检查是否过期
	 * 
	 * @param integer $timestamp
	 * @param integer $expiration
	 * @return boolean
	 */
	private function _checkExpired($timestamp, $expiration) {
		$result = false;
		if ($expiration !== 0) {
			$timeDiff = time() - $timestamp;
			($timeDiff > $expiration) ? $result = true : $result = false;
		}
		return $result;
	}

	/**
	 * 检查目录
	 * 
	 * @return boolean
	 */
	private function _checkCacheDir() {
		if (!is_dir($this->_cachepath) && !mkdir($this->_cachepath, 0775, true)) {
			throw new Exception('无法创建文件夹 ' . $this->_cachepath);
		} elseif (!is_readable($this->_cachepath) || !is_writable($this->_cachepath)) {
			if (!chmod($this->_cachepath, 0775)) {
				throw new Exception($this->_cachepath . ' 目录无读写权限');
			}
		}
		return true;
	}

	/**
	 * @param $method
	 * @param $arguments
	 * @throws \BadMethodCallException
	 * @return mixed
	 */
	public function __call($method, $arguments) {
		if ($method == 'has') {
			return call_user_func_array([$this, 'isCached'], [$arguments]);
		}
		if (substr($method, 0, 3) == 'set') {
			$key = substr($method, 3);
			if (! empty($key))
				$this->_data[$key] = array_shift($arguments);
			else
				$this->_data = array_shift($arguments);
			return $this;
		}
		
		if (substr($method, 0, 3) == 'get') {
			$key = substr($method, 3);
			if (! empty($key))
				return isset($this->_data[$key]) ? $this->_data[$key] : null;
			else
				return $this->_data;
		}
		
		throw new \BadMethodCallException(sprintf('The function "%s" does not exist!', $method));
	}

}
