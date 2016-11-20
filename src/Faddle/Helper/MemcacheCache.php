<?php namespace Faddle\Helper;

class MemcacheCache extends Cache {

	public function __construct($options=array()) {
		if (!extension_loaded('memcache')) {
			throw new \Exception('The memcache PHP extension must be loaded to use this driver.');
		}
		
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
		
	}

	/**
	 * 读取缓存
	 */
	public function get($key) {
		return $this->handler->get($this->options['prefix'].$key);
	}

	/**
	 * 写入缓存
	 */
	public function set($key, $value,$expire=null) {
		if (is_null($expire)) {
			$expire = $this->options['expire'];
		}
		$key = $this->options['prefix'].$key;
		if ($this->handler->set($key, $value, 0, $expire)) {
			if ($this->options['length'] > 0) {
				// 记录缓存队列
				$this->queue($key);
			}
			return true;
		}
		return false;
	}

	/**
	 * 删除缓存
	 */
	public function delete($key, $ttl=false) {
		$name = $this->options['prefix'].$key;
		return $ttl === false ?
			$this->handler->delete($name) :
			$this->handler->delete($name, $ttl);
	}

	/**
	 * 清除缓存
	 */
	public function clear() {
		return $this->handler->flush();
	}


}