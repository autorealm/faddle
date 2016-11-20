<?php namespace Faddle\Storage;

/**
 * Array 数据存储类
 * @since 2015-10-21
 */
class ArrayStorage extends \StdClass implements \ArrayAccess {

	/**
	 * 扩展数据指针
	 *
	 * @access private
	 * @var array
	 */
	private $_storage = array();

	/**
	 * 构造函数，设置扩展数据
	 *
	 * @access public
	 * @param  array  $storage  External session storage (example: $_SESSION)
	 */
	public function __construct(array &$storage) {
		$this->_storage =& $storage;
		// Load dynamically existing session variables into object properties
		foreach ($storage as $key => $value) {
			$this->$key = $value;
		}
	}

	public function __set($key, $value) {
		$this->_storage[$key] = $value;
	}

	public function &__get($key) {
		return $this->_storage[$key];
	}

	public function __isset($key) {
		return isset($this->_storage[$key]);
	}

	public function __unset($key) {
		unset($this->_storage[$key]);
	}

	public function get($key, $default=null) {
		return isset($this->_storage[$key]) ? $this->_storage[$key] : $default;
	}
	
	public function bind($key, &$value) {
		$this->_storage[$key] = &$value;
	}

	public function offsetExists($offset) {
		return $this->__isset($offset);
	}

	public function &offsetGet($offset) {
		return $this->__get($offset);
	}

	public function offsetSet($offset, $value) {
		return $this->__set($offset, $value);
	}

	public function offsetUnset($offset) {
		return $this->__unset($offset);
	}

	/**
	 * 添加一个闪存数据
	 *
	 * @access public
	 * @param  string  $key
	 * @param  string  $data
	 */
	public function setFlash($key, $data) {
		if (! isset($this->_flash)) {
			$this->_flash = array();
		}
		
		$this->_flash[$key] = $data;
	}

	/**
	 * 获取闪存数据
	 *
	 * @access public
	 * @param  string  $key
	 * @return string
	 */
	public function getFlash($key) {
		$data = '';
		
		if (isset($this->_flash[$key])) {
			$data = $this->_flash[$key];
			unset($this->_flash[$key]);
		}
		
		return $data;
	}

	/**
	 * 获取全部存储数据
	 * @access public
	 * @return array
	 */
	public function getAll() {
		$data = get_object_vars($this);
		unset($data['_storage']);
		$data = array_merge($this->_storage, $data);
		return $data;
	}

	/**
	 * 清空所有存储数据
	 *
	 * @access public
	 */
	public function clear() {
		$data = get_object_vars($this);
		foreach (array_keys($data) as $property) {
			unset($this->$property);
		}
		foreach ($this->_storage as $key => $value) {
			unset($this->_storage[$key]);
		}
	}

	public function __destruct() {
		$this->_storage = $this->getAll();
	}


}