<?php namespace Faddle\Storage\Cache;


abstract class BaseCache {

	var $tmp = array();
	// default options, this will be merge to Driver's Options
	var $config = array();
	var $fallback = false;
	var $instance;

	public function set($key, $value = "", $time = 0, $option = array()) {
		/* Infinity Time */
		if((Int)$time <= 0) {
			// 5 years, however memcached or memory cached will gone when u restart it
			// just recommended for sqlite. files
			$time = 3600*24*365*5;
		}
		$object = array(
			"data" => $value,
			"time"  => time(),
			"expired"  => time() + (int)$time,
		);
		return $this->_set($key,$object,$time,$option);
		
	}

	public function get($key, $option = array()) {
		$object = $this->_get($key, $option);
		
		if($object == null) {
			return null;
		}
		
		$value = isset($object['data']) ? $object['data'] : null;
		return $value;
	}

	function getInfo($key, $option = array()) {
		$object = $this->_get($key, $option);
		if($object == null) {
			return null;
		}
		return $object;
	}

	function delete($key, $option = array()) {
		return $this->_delete($key, $option);
	}

	function stats($option = array()) {
		return $this->_stats($option);
	}

	function clean($option = array()) {
		return $this->_clean($option);
	}

	function has($key) {
		if(method_exists($this,"_has")) {
			return $this->_has($keyword);
		}
		$data = $this->get($keyword);
		if($data == null) {
			return false;
		} else {
			return true;
		}
	}

	function increment($key, $step = 1 , $option = array()) {
		$object = $this->getInfo($key);
		if($object == null) {
			return false;
		} else {
			$value = (Int)$object['data'] + (Int)$step;
			$time = $object['expired'] - time();
			$this->set($key, $value, $time, $option);
			return true;
		}
	}

	function decrement($key, $step = 1 , $option = array()) {
		$object = $this->getInfo($key);
		if($object == null) {
			return false;
		} else {
			$value = (Int)$object['data'] - (Int)$step;
			$time = $object['expired'] - time();
			$this->set($key, $value, $time, $option);
			return true;
		}
	}

	/*
	 * Extend more time
	 */
	function touch($key, $time = 300, $option = array()) {
		$object = $this->getInfo($key);
		if($object == null) {
			return false;
		} else {
			$value = $object['data'];
			$time = $object['expired'] - time() + $time;
			$this->set($keyword, $value, $time, $option);
			return true;
		}
	}


	/**
	 * Check if this Cache driver is available for server or not
	 */
	abstract function __construct($config = array());

	abstract function _checkdriver();

	/**
	 * set a obj to cache
	 */
	abstract function _set($key, $value = "", $time = 300, $option = array() );

	/**
	 * return null or value of cache
	 */
	abstract function _get($key, $option = array());

	/**
	 * Show stats of caching
	 * Return array ("info","size","data")
	 */
	abstract function _stats($option = array());

	/**
	 * Delete a cache
	 */
	abstract function _delete($key, $option = array());

	/**
	 * Clean up whole cache
	 */
	abstract function _clean($option = array());

	
	public function mset($list = array()) {
		foreach($list as $array) {
			$this->set($array[0], isset($array[1]) ? $array[1] : 0, isset($array[2]) ? $array[2] : array());
		}
	}

	public function mget($list = array()) {
		$res = array();
		foreach($list as $array) {
			$name = $array[0];
			$res[$name] = $this->get($name, isset($array[1]) ? $array[1] : array());
		}
		return $res;
	}

	public function mgetInfo($list = array()) {
		$res = array();
		foreach($list as $array) {
			$name = $array[0];
			$res[$name] = $this->getInfo($name, isset($array[1]) ? $array[1] : array());
		}
		return $res;
	}

	public function mdelete($list = array()) {
		foreach($list as $array) {
			$this->delete($array[0], isset($array[1]) ? $array[1] : array());
		}
	}

	public function mhas($list = array()) {
		$res = array();
		foreach($list as $array) {
			$name = $array[0];
			$res[$name] = $this->has($name);
		}
		return $res;
	}

	public function mincrement($list = array()) {
		$res = array();
		foreach($list as $array) {
			$name = $array[0];
			$res[$name] = $this->increment($name, $array[1], isset($array[2]) ? $array[2] : array());
		}
		return $res;
	}

	public function mdecrement($list = array()) {
		$res = array();
		foreach($list as $array) {
			$name = $array[0];
			$res[$name] = $this->decrement($name, $array[1], isset($array[2]) ? $array[2] : array());
		}
		return $res;
	}

	public function mtouch($list = array()) {
		$res = array();
		foreach($list as $array) {
			$name = $array[0];
			$res[$name] = $this->touch($name, $array[1], isset($array[2]) ? $array[2] : array());
		}
		return $res;
	}


	public function setup($config_name, $value = "") {
		/* Config for class */
		if(is_array($config_name)) {
			$this->config = $config_name;
		} else {
			$this->config[$config_name] = $value;
		}
		return $this->config;
	}

	/* Magic Functions */

	function __get($name) {
		return $this->get($name);
	}

	function __set($name, $v) {
		if(isset($v[1]) && is_numeric($v[1])) {
			return $this->set($name,$v[0],$v[1], isset($v[2]) ? $v[2] : array() );
		} else {
			throw new Exception("Example ->$name = array('VALUE', 300);",98);
		}
	}

	public function __call($name, $args) {
		return call_user_func_array(array( $this->instance, $name), $args);
	}


	/*
	 * Base Functions
	 */

	protected function readfile($file) {
		if(function_exists("file_get_contents")) {
			return @file_get_contents($file);
		} else {
			$string = "";
			$file_handle = @fopen($file, "r");
			if(!$file_handle) {
				throw new Exception("Can't Read File",96);
			}
			while (!feof($file_handle)) {
				$line = fgets($file_handle);
				$string .= $line;
			}
			fclose($file_handle);
			return $string;
		}
	}


	/*
	 * return PATH for Files & PDO only
	 */

	public function getPath($create_path = false) {
		$config = $this->config;
		if (!isset($config['path']) || $config['path'] == '') {
			$tmp_dir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
			$path = $tmp_dir;
		} else {
			$path = $config['path'];
		}
		if($create_path) {
			if(! file_exists($path)) {
				@mkdir($path, 0777);
			}
			if(! is_writable($path)) {
				@chmod($path, 0777);
			}
			if(!@file_exists($path) || !@is_writable($path)) {
				throw new Exception("PLEASE CREATE OR CHMOD ".$path." - 0777 OR ANY WRITABLE PERMISSION!", 92);
			}
		}
		return realpath($path);
	}


	/*
	 * Object for Files & SQLite
	 */
	protected function encode($data) {
		return serialize($data);
	}

	protected function decode($value) {
		$x = @unserialize($value);
		if($x == false) {
			return $value;
		} else {
			return $x;
		}
	}


}