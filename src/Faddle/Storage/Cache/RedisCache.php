<?php namespace Faddle\Storage\Cache;

use Faddle\Storage\Cache\BaseCache;

class RedisCache extends BaseCache {

	var $checked_redis = false;

	protected function _checkdriver() {
		// Check memcache
		if(class_exists("Redis")) {
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
		if(class_exists("Redis")) {
			$this->instance = new Redis();
		}

	}

	protected function connectServer() {

		$server = isset($this->config['redis']) ? $this->config['redis'] : array(
																					"host" => "127.0.0.1",
																					"port"  =>  "6379",
																					"password"  =>  "",
																					"database"  =>  "",
																					"timeout"   => "1",
																				);

		if($this->checked_redis === false) {

			$host = $server['host'];

			$port = isset($server['port']) ? (Int)$server['port'] : "";
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



			if(!$this->instance->connect($host,(int)$port,(Int)$timeout)) {
				$this->checked_redis = true;
				$this->fallback = true;
				return false;
			} else {
				if($database!="") {
					$this->instance->select((Int)$database);
				}
				$this->checked_redis = true;
				return true;
			}
		}

		return true;
	}

	protected function _set($key, $value = "", $time = 300, $option = array() ) {
		if($this->connectServer()) {
			$value = $this->encode($value);
			if (isset($option['skipExisting']) && $option['skipExisting'] == true) {
				return $this->instance->set($key, $value, array('xx', 'ex' => $time));
			} else {
				return $this->instance->set($key, $value, $time);
			}
		} else {
			return $this->backup()->set($key, $value, $time, $option);
		}
	}

	protected function _get($key, $option = array()) {
		if($this->connectServer()) {
			// return null if no caching
			// return value if in caching'
			$x = $this->instance->get($key);
			if($x == false) {
				return null;
			} else {

				return $this->decode($x);
			}
		} else {
			$this->backup()->get($key, $option);
		}

	}

	protected function _delete($key, $option = array()) {

		if($this->connectServer()) {
			$this->instance->delete($key);
		}

	}

	protected function _stats($option = array()) {
		if($this->connectServer()) {
			$res = array(
				"info"  => "",
				"size"  =>  "",
				"data"  => $this->instance->info(),
			);

			return $res;
		}

		return array();

	}

	protected function _clean($option = array()) {
		if($this->connectServer()) {
			$this->instance->flushDB();
		}

	}

	protected function _has($key) {
		if($this->connectServer()) {
			$x = $this->instance->exists($key);
			if($x == null) {
				return false;
			} else {
				return true;
			}
		} else {
			return $this->backup()->isExisting($key);
		}

	}



}