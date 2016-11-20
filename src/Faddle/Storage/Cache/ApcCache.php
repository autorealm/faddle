<?php namespace Faddle\Storage\Cache;

use Faddle\Storage\Cache\BaseCache;

class ApcCache extends BaseCache {

	protected function _checkdriver() {
		// Check apc
		if(extension_loaded('apc') && ini_get('apc.enabled')) {
			return true;
		} else {
			$this->fallback = true;
			return false;
		}
	}

	public function __construct($config = array()) {
		$this->setup($config);
		if(!$this->_checkdriver() && !isset($config['skipError'])) {
			$this->fallback = true;
		}
	}

	protected function _set($key, $value = "", $time = 300, $option = array() ) {
		if(isset($option['skipExisting']) && $option['skipExisting'] == true) {
			return apc_add($key, $value, $time);
		} else {
			return apc_store($key, $value, $time);
		}
	}

	protected function _get($key, $option = array()) {
		$data = apc_fetch($key, $bo);
		if($bo === false) {
			return null;
		}
		return $data;
	}

	protected function _delete($key, $option = array()) {
		return apc_delete($key);
	}

	protected function _stats($option = array()) {
		$res = array(
			"info" => "",
			"size"  => "",
			"data"  =>  "",
		);
		
		try {
			$res['data'] = apc_cache_info("user");
		} catch(Exception $e) {
			$res['data'] =  array();
		}
		
		return $res;
	}

	protected function _clean($option = array()) {
		@apc_clear_cache();
		@apc_clear_cache("user");
	}

	protected function _has($key) {
		if(apc_exists($key)) {
			return true;
		} else {
			return false;
		}
	}

}