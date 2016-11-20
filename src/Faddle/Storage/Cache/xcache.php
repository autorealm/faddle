<?php namespace Faddle\Storage\Cache;

use Faddle\Storage\Cache\BaseCache;

class XCache extends BaseCache {

	protected function _checkdriver() {
		// Check xcache
		if(extension_loaded('xcache') && function_exists("xcache_get")) {
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

	protected function _set($keyword, $value = "", $time = 300, $option = array() ) {
		if(isset($option['skipExisting']) && $option['skipExisting'] == true) {
			if(!xcache_isset($keyword)) {
				return xcache_set($keyword,serialize($value),$time);
			}
		} else {
			return xcache_set($keyword,serialize($value),$time);
		}
		return false;
	}

	protected function _get($keyword, $option = array()) {
		$data = unserialize(xcache_get($keyword));
		if($data === false || $data == "") {
			return null;
		}
		return $data;
	}

	protected function _delete($keyword, $option = array()) {
		return xcache_unset($keyword);
	}

	protected function _stats($option = array()) {
		$res = array(
			"info"  =>  "",
			"size"  =>  "",
			"data"  =>  "",
		);
		try {
			$res['data'] = xcache_list(XC_TYPE_VAR,100);
		} catch(Exception $e) {
			$res['data'] = array();
		}
		return $res;
	}

	protected function _clean($option = array()) {
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i < $cnt; $i++) {
			xcache_clear_cache(XC_TYPE_VAR, $i);
		}
		return true;
	}

	protected function _has($keyword) {
		if(xcache_isset($keyword)) {
			return true;
		} else {
			return false;
		}
	}

}