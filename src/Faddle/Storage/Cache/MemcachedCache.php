<?php namespace Faddle\Storage\Cache;

use Faddle\Storage\Cache\BaseCache;

class MemcachedCache extends BaseCache {

	var $instance;
	var $checked = array();

	protected function _checkdriver() {
		if(class_exists("Memcached")) {
			return true;
		}
		$this->fallback = true;
	   return false;
	}

	function __construct($config = array()) {
		$this->setup($config);
		if(!$this->_checkdriver() && !isset($config['skipError'])) {
			$this->fallback = true;
		}
		if(class_exists("Memcached")) {
			$this->instance = new Memcached();
		} else {
			$this->fallback = true;
		}

	}

	protected function connect() {
		if($this->checkdriver() == false) {
			return false;
		}
		$s = $this->config['memcache'];
		if(count($s) < 1) {
			$s = array(
				array('127.0.0.1', 11211, 100),
			);
		}
		foreach($s as $server) {
			$name = isset($server[0]) ? $server[0] : '127.0.0.1';
			$port = isset($server[1]) ? $server[1] : 11211;
			$sharing = isset($server[2]) ? $server[2] : 0;
			$checked = $name."_".$port;
			if(!isset($this->checked[$checked])) {
				try {
					if($sharing >0 ) {
						if(!$this->instance->addServer($name,$port,$sharing)) {
							$this->fallback = true;
						}
					} else {
						if(!$this->instance->addServer($name,$port)) {
							$this->fallback = true;
						}
					}
					$this->checked[$checked] = 1;
				} catch (Exception $e) {
					$this->fallback = true;
				}
			}
		}
	}

	protected function _set($key, $value = "", $time = 300, $option = array()) {
		$this->connect();
		if(isset($option['isExisting']) && $option['isExisting'] == true) {
			return $this->instance->add($key, $value, time() + $time );
		} else {
			return $this->instance->set($key, $value, time() + $time );
		}
	}

	protected function _get($key, $option = array()) {
		$this->connect();
		$x = $this->instance->get($key);
		if($x == false) {
			return null;
		} else {
			return $x;
		}
	}

	protected function _delete($key, $option = array()) {
		$this->connect();
		$this->instance->delete($key);
	}

	protected function _stats($option = array()) {
		$this->connect();
		$res = array(
			"info" => "",
			"size"  =>  "",
			"data"  => $this->instance->getStats(),
		);
		
		return $res;
	}

	protected function _clean($option = array()) {
		$this->connect();
		$this->instance->flush();
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
