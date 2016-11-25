<?php namespace Faddle\Helper;

if ( ! defined('DATA_CACHE_COMPRESS')) {
	define('DATA_CACHE_COMPRESS', TRUE);
}
if ( ! defined('DATA_CACHE_CHECK')) {
	define('DATA_CACHE_CHECK', TRUE);
}

/**
 * 缓存类
 * 
 */
class Cache {
	protected $handler;
	protected $options = array(
			'prefix' => 't_',
			'expire' => 7200,
			'length' => 0,
			'cachedir'   => '',
		);

	protected function connect($type='', $options=array()) {
		if(empty($type)) $type = 'file';
		$class = strpos($type,'Cache') ? $type : ucwords(strtolower($type)).'Cache';
		if(class_exists(__NAMESPACE__ . '/' . $class))
			$this->handler = new $class($options);
		elseif($type == 'apc' && extension_loaded('apc') && ini_get('apc.enabled'))
			$this->handler = array('get' => 'apc_fetch', 'set' => 'apc_store', 'has' => 'apc_exists', 'dec' => 'apc_dec', 
				'inc' => 'apc_inc', 'cas' => 'apc_cas', 'remove' => 'apc_delete', 'clear' => 'apc_clear_cache');
		elseif($type == 'apcu' && extension_loaded('apcu') && ini_get('apcu.enabled'))
			$this->handler = array('get' => 'apcu_fetch', 'set' => 'apcu_store', 'has' => 'apcu_exists', 'dec' => 'apcu_dec', 
				'inc' => 'apcu_inc', 'cas' => 'apcu_cas', 'remove' => 'apcu_delete', 'clear' => 'apcu_clear_cache');
		elseif($type == 'xcache' && extension_loaded('xcache') && function_exists('xcache_get'))
			$this->handler = array('get' => 'xcache_get', 'set' => 'xcache_set', 'has' => 'xcache_isset', 'dec' => 'xcache_dec', 
				'inc' => 'xcache_inc', 'remove' => 'xcache_unset', 'clear' => function() {
						$cnt = xcache_count(XC_TYPE_VAR);
						for ($i=0; $i < $cnt; $i++) {
							xcache_clear_cache(XC_TYPE_VAR, $i);
						}
						return true;
					});
		elseif($type == 'memcached' && extension_loaded('memcached'))
			$this->handler = new Memcached(isset($options['prefix']) ? $options['prefix'] : '', $options['servers']);
		elseif($type == 'memcache' && extension_loaded('memcache')) {
			$options = array_merge(array (
				'host' => '127.0.0.1',
				'port' => 11211,
				'persistent' => false,
				'weight' => 1,
				'timeout' => false,
				'retry_interval' => 15
			), $options);
			
			$func = $options['persistent'] ? 'pconnect' : 'connect';
			$this->handler = new \Memcache;
			$options['timeout'] === false ?
				$this->handler->$func($options['host'], $options['port']) :
				$this->handler->$func($options['host'], $options['port'], $options['timeout']);
			
		} else throw new \Exception('Cache Driver not exists:'.$type);
		
		return $this->handler;
	}

	/**
	 * 获取缓存类实例
	 * 
	 */
	public static function getInstance($type='', $options=array()) {
		static $_instance = array();
		$guid = $type.md5(serialize($options));
		if (!isset($_instance[$guid])) {
			$obj = new self();
			$_instance[$guid] = $obj->connect($type, $options);
		}
		
		return $_instance[$guid];
	}

	public function __get($key) {
		return $this->get($key);
	}

	public function __set($key, $value) {
		return $this->set($key, $value);
	}

	public function __isset($key) {
		$this->has($key);
	}

	public function __unset($key) {
		$this->remove($key);
	}

	public function __clear() {
		return $this->clear();
	}

	public function offsetExists($offset) {
		return $this->has($offset);
	}
	public function offsetSet($offset, $value) {
		$this->set($offset, $value);
	}
	public function offsetGet($offset) {
		return $this->get($offset);
	}
	public function offsetUnset($offset) {
		$this->remove($offset);
	}

	public function options($key, $value) {
		if (isset($key) and isset($value))
			$this->options[$key] = $value;
		else if (isset($key)) {
			if (is_array($key))
				$this->options = (array)$key;
			else
				return $this->options[$key];
		}
		return  $this->options;
	}

	public function __call($method, $args) {
		//调用缓存类型自带的方法
		if (is_array($this->handler)) {
			if (in_array($method, $this->handler))
				return call_user_func_array($this->handler[$method], $args);
			return false;
		}
		if ($method == 'remove' and method_exists($this->handler, 'delete')) {
			return call_user_func_array(array($this->handler, 'delete'), $args);
		} elseif ($method == 'clear' and method_exists($this->handler, 'flush')) {
			return call_user_func_array(array($this->handler, 'flush'), $args);
		} elseif ($method == 'has' and method_exists($this->handler, 'get')) {
			return $this->handler->get($args[0]) !== false;
		}
		if (method_exists($this->handler, $method)) {
			return call_user_func_array(array($this->handler, $method), $args);
		} else {
			throw new \Exception('类 '.__CLASS__.' : '.$method.' 方法不存在！');
			return false;
		}
	}

	/**
	 * 队列缓存
	 */
	protected function queue($key, $value) {
		if (!$value) {
			$value = array();
		}
		//进列
		if (!array_search($key, $value)) array_push($value, $key);
		if (count($value) > $this->options['length']) {
			//出列
			$key = array_shift($value);
			//删除缓存
			$this->delete($key);
		}
		
		return true;
	}
	
}
