<?php namespace Faddle\Storage\Cache;

use Faddle\Storage\Cache\BaseCache;

class PredisCache extends BaseCache {

	var $checked_redis = false;

	function _checkdriver() {
		if (! class_exists("\\Predis\\Client")) {
			try {
				\Predis\Autoloader::register();
			} catch(Exception $e) {
				$this->fallback = true;
				return false;
			}
		}
		return true;
	}

	function __construct($config = array()) {
		$this->setup($config);
		if(!$this->_checkdriver() && !isset($config['skipError'])) {
			$this->fallback = true;
		}
	}

	function connect() {
		$server = isset($this->config['redis']) ? $this->config['redis']
			: array(
				'host' => '127.0.0.1',
				'port'  => '6379',
				'password'  => '',
				'database'  => ''
				);
		if($this->checked_redis === false) {
			$c = array(
				"host"  => $server['host'],
			);
			$port = isset($server['port']) ? $server['port'] : "";
			if($port!="") {
				$c['port'] = $port;
			}
			$password = isset($server['password']) ? $server['password'] : "";
			if($password!="") {
				$c['password'] = $password;
			}
			$database = isset($server['database']) ? $server['database'] : "";
			if($database!="") {
				$c['database'] = $database;
			}
			$timeout = isset($server['timeout']) ? $server['timeout'] : "";
			if($timeout!="") {
				$c['timeout'] = $timeout;
			}
			$read_write_timeout = isset($server['read_write_timeout']) ? $server['read_write_timeout'] : "";
			if($read_write_timeout!="") {
				$c['read_write_timeout'] = $read_write_timeout;
			}
			$this->instance = new \Predis\Client($c);
			$this->checked_redis = true;
			if(!$this->instance) {
				$this->fallback = true;
				return false;
			} else {
				return true;
			}
		}
		return true;
	}

	function _set($key, $value = "", $time = 300, $option = array() ) {
		if($this->connect()) {
			$value = $this->encode($value);
			if (isset($option['skipExisting']) && $option['skipExisting'] == true) {
				return $this->instance->setex($key, $time, $value);
			} else {
				return $this->instance->setex($key, $time, $value );
			}
		} else {
			return false;
		}
	}

	function _get($key, $option = array()) {
		if($this->connect()) {
			$x = $this->instance->get($key);
			if($x == false) {
				return null;
			} else {
				return $this->decode($x);
			}
		} else {
			return false;
		}

	}

	function _delete($key, $option = array()) {
		if($this->connect()) {
			$this->instance->del($key);
		}
	}

	function _stats($option = array()) {
		if($this->connect()) {
			$res = array(
				"info"  => "",
				"size"  =>  "",
				"data"  => $this->instance->info(),
			);
			return $res;
		}
		
		return array();

	}

	function _clean($option = array()) {
		if($this->connect()) {
			$this->instance->flushDB();
		}
	}

	function _has($key) {
		if($this->connect()) {
			$x = $this->instance->exists($key);
			if($x == null) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

}
