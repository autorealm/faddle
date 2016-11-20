<?php namespace Faddle\Helper;

/**
 * 数据缓存类
 */
class OneCacheHelper {
	const CONFIG_EXIT = "[CACHEFILE-1.00]\r\n";
	public $driver = null;
	public $expire = 0;
	public $autosave = true; //是否自动保存数据，为否时将在销毁对象时保存。
	private $data = array();
	private $file;

	/**
	 * 构造函数
	 * @param $file string 缓存文件路径|索引缓存数据的键名
	 * @param $driver object 可选，缓存驱动器，默认空
	 * @param $expire int 可选，过期时间，默认不过期
	 */
	function __construct($file, $driver=null, $expire=0) {
		$this->driver = $driver;
		$this->expire = $expire;
		$this->file = $file;
		$data = self::load($file, $this->driver);
		if (is_array($data)) $this->data =$data;
	}
	
	public function __destruct() {
		if (!$this->autosave) $this->_save();
	}

	private function _save() {
		return self::save($this->file, $this->data, $this->driver, $this->expire);
	}
	
	/**
	 * 重置所有数据；不传参数代表清空数据
	 */
	public function reset($list = array()) {
		$this->data = $list;
		$this->_save();
	}

	/**
	 * 添加一条数据，不能重复；如果已存在则返回false;1k次/s
	 */
	public function add($k, $v) {
		if (!isset($this->data[$k])) {
			$this->data[$k] = $v;
			if ($this->autosave) $this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 设置一条数据
	 */
	public function set($k, $v) {
		$this->data[$k] = $v;
		if ($this->autosave) $this->_save();
	}

	/**
	 * 获取数据;不存在则返回false;100w次/s
	 * $k null 不传则返回全部;
	 * $k string 为字符串；则根据key获取数据，只有一条数据
	 * $search_value 设置时；表示以查找的方式筛选数据筛选条件为 $key=$k 值为$search_value的数据；多条
	 */
	public function get($k = '', $default_value = null, $search_value = false) {
		if ($k === '')
			return $this->data;
		
		$search = array();
		if ($search_value === false) {
			if (is_array($k)) {
				// 多条数据获取
				$num = count($k);
				for ($i = 0; $i < $num; $i++) {
					$search[$k[$i]] = $this->data[$k[$i]];
				}
				return $search;
			} else if (isset($this->data[$k])) {
				// 单条数据获取
				return $this->data[$k];
			}
		} else {
			// 查找内容数据方式获取；返回多条
			foreach ($this->data as $key => $val) {
				if ($val[$k] == $search_value) {
					$search[$key] = $this->data[$key];
				}
			}
			return $search;
		}
		return $default_value;
	}

	/**
	 * 移除一条数据
	 */
	public function remove($k) {
		if (isset($this->data[$k])) {
			unset($this->data[$k]);
			if ($this->autosave) $this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 更新数据;不存在;或者任意一条不存在则返回false;不进行保存
	 * $k $v string 为字符串；则根据key只更新一条数据
	 * $k $v array array($key1,$key2,...),array($value1,$value2,...) 则表示更新多条数据
	 * $search_value 设置时；表示以查找的方式更新数据中的数据
	 */
	public function update($k, $v, $search_value = false) {
		if ($search_value === false) {
			if (is_array($k)) {
				// 多条数据更新
				$num = count($k);
				for ($i = 0; $i < $num; $i++) {
					$this->data[$k[$i]] = $v[$i];
				}
				if ($this->autosave) $this->_save();
				return true;
			} else if (isset($this->data[$k])) {
				// 单条数据更新
				$this->data[$k] = $v;
				if ($this->autosave) $this->_save();
				return true;
			}
		} else {
			// 查找方式更新；更新多条
			foreach ($this->data as $key => $val) {
				if ($val[$k] == $search_value) {
					$this->data[$key][$k] = $v;
				}
			}
			if ($this->autosave) $this->_save();
			return true;
		}
		return false;
	}

	/*
	 * 替换方式更新；满足key更新的需求
	 */
	public function replace_update($key_old, $key_new, $value_new) {
		if (isset($this->data[$key_old])) {
			$value = $this->data[$key_old];
			unset($this->data[$key_old]);
			$this->data[$key_new] = $value_new;
			if ($this->autosave) $this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 删除;不存在返回false
	 */
	public function delete($k, $search_value = false) {
		if ($search_value === false) {
			if (is_array($k)) {
				// 多条数据更新
				$num = count($k);
				for ($i = 0; $i < $num; $i++) {
					unset($this->data[$k[$i]]);
				}
				if ($this->autosave) $this->_save();
				return true;
			} else if (isset($this->data[$k])) {
				// 单条数据删除
				unset($this->data[$k]);
				if ($this->autosave) $this->_save();
				return true;
			}
		} else {
			// 查找内容数据方式删除；删除多条
			foreach ($this->data as $key => $val) {
				if ($val[$k] == $search_value) {
					unset($this->data[$key]);
				}
			}
			if ($this->autosave) $this->_save();
			return true;
		}
		return false;
	}

	/**
	 * 排序
	 */
	public static function array_sort(&$arr, $key, $type = 'asc') {
		$keysvalue = $new_array = array();
		foreach ($arr as $k => $v) {
			$keysvalue[$k] = $v[$key];
		}
		if ($type == 'asc') {
			asort($keysvalue);
		} else {
			arsort($keysvalue);
		}
		reset($keysvalue);
		foreach ($keysvalue as $k => $v) {
			$new_array[$k] = $arr[$k];
		}
		return $new_array;
	}

	/**
	 * 加载数据；并解析成程序数据
	 */
	public static function load($file, $driver=null, $ftime=0) { // 10000次需要4s 数据量差异不大。
		if (is_object($driver)) {
			if (method_exists($driver, 'get')) {
				$str = $driver->get($file);
			} else if (method_exists($driver, 'load')) {
				$str = $driver->load($file);
			}
		} else {
			if (!file_exists($file))
				touch($file);
			$str = file_get_contents($file);
		}
		if (! $str) return false;
		$str = substr($str, strlen(self::CONFIG_EXIT));
		$stored = unserialize($str);
		if (! is_array($stored)) return false;
		if ($stored['expire'] > 0) {
			//检查是否过期
			if (time() > $stored['mtime'] + $stored['expire']) {
				return false;
			}
		} else if (($ftime = intval($ftime)) > 0) {
			if ($ftime > $stored['mtime']) {
				//文件已经过期
				return false;
			} else {
				return $stored['data'];
			}
		}
		$data = $stored['data'];
		//$data = json_decode($str, true);
		//if (is_null($data)) $data = array();
		
		return $data;
	}

	/**
	 * 保存数据
	 */
	public static function save($file, $data, $driver=null, $expire=0) { // 10000次需要6s
		if (!$file) return false;
		//if (intval($expire) <= 0) $expire = $this->expire;
		//if (is_array($data)) $data = json_encode($data);
		$contents = array(
				'mtime'		=> time(),
				'expire'	=> intval($expire),
				'data'		=> $data
		);
		$contents = self::CONFIG_EXIT . serialize($contents);
		if (is_object($driver)) {
			$ret = false;
			if (method_exists($driver, 'set')) {
				$ret = $driver->set($file, $contents);
			} else if (method_exists($driver, 'put')) {
				$ret = $driver->put($file, $contents);
			} else if (method_exists($driver, 'add')) {
				$ret = $driver->add($file, $contents);
			}
			return $ret;
		} else {
			if ($fp = fopen($file, "w")) {
				if (flock($fp, LOCK_EX)) { // 进行排它型锁定
					$str = $contents;
					fwrite($fp, $str);
					fflush($fp); // flush output before releasing the lock
					flock($fp, LOCK_UN); // 释放锁定
				}
				fclose($fp);
				return true;
			}
		}
		return false;
	}

}
