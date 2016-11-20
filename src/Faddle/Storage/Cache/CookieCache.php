<?php namespace Faddle\Storage\Cache;

use Faddle\Storage\Cache\BaseCache;


class CookieCache extends BaseCache {


	protected function _checkdriver() {
		if(function_exists("setcookie")) {
			return true;
		}
		$this->fallback = true;
		return false;
	}

	public function __construct($config = array()) {
		$this->setup($config);
		if(!$this->_checkdriver() && !isset($config['skipError'])) {
			$this->fallback = true;
		}
	}

	protected function connect() {
		// for cookie check output
		if(!isset($_COOKIE['_cookie_cache_'])) {
			if(!@setcookie("_cookie_cache_", 1, 10)) {
				$this->fallback = true;
			}
		}
	}

	protected function _set($key, $value = "", $time = 300, $option = array() ) {
		$this->connect();
		$key = "cc_".$key;
		return @setcookie($key, $this->encode($value), $time, "/");
	}

	protected function _get($key, $option = array()) {
		$this->connect();
		// return null if no caching
		// return value if in caching
		$key = "cc_".$key;
		$x = isset($_COOKIE[$key]) ? $this->decode($_COOKIE[$key]) : false;
		if($x == false) {
			return null;
		} else {
			return $x;
		}
	}

	protected function _delete($key, $option = array()) {
		$this->connect();
		$key = "cc_".$key;
		@setcookie($key, null, -10);
		$_COOKIE[$key] = null;
	}

	protected function _stats($option = array()) {
		$this->connect();
		$res = array(
			"info"  => "",
			"size"  =>  "",
			"data"  => $_COOKIE
		);
		return $res;
	}

	protected function _clean($option = array()) {
		$this->connect();
		foreach($_COOKIE as $key => $value) {
			if(strpos($key,"_cookie_cache_") !== false) {
				@setcookie($key, null, -10);
				$_COOKIE[$key] = null;
			}
		}
	}

	protected function _has($key) {
		$this->connect();
		$x = $this->get($key);
		if($x == null) {
			return false;
		} else {
			return true;
		}
	}



}