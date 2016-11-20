<?php namespace Faddle\Support\Queue;

defined('MQ_POOL') ?: define('MQ_POOL', 'localhost:11211:5');
defined('MQ_TTL') ?: define('MQ_TTL', 0);

use Memcached;
use Exception;

class MQ {
	
	private static $memcached = NULL;
	
	private function __construct() {}
	
	private function __clone() {}
	
	private static function getInstance() {
		if(!self::$memcached) self::init();
		return self::$memcached;
	}
	
	private static function init() {
		$memcached = new Memcached;
		$servers = explode(',', MQ_POOL);
		foreach($servers as $server) {
			list($host, $port, $weight) = explode(':', $server);
			$memcached->addServer($host, $port, $weight);
		}
		self::$memcached = $memcached;
	}
	
	public static function exists($queue) {
		$memcached = self::getInstance();
		$head = $memcached->get($queue.'_head');
		$tail = $memcached->get($queue.'_tail');
		
		if($head >= $tail || $head === false || $tail === false) 
			return false;
		else 
			return true;
	}

	public static function dequeue($queue, $after_id=false, $till_id=false) {
		$memcached = self::getInstance();
		
		if($after_id === false && $till_id === false) {
			$tail = $memcached->get($queue.'_tail');
			if(($id = $memcached->increment($queue.'_head')) === false) 
				return false;
			
			if($id <= $tail) {
				return $memcached->get($queue.'_'.($id-1));
			} else {
				$memcached->decrement($queue.'_head');
				return false;
			}
		}
		else if($after_id !== false && $till_id === false) {
			$till_id = $memcached->get($queue.'_tail');
		}
		
		$item_keys = array();
		for($i=$after_id+1; $i<=$till_id; $i++)
			$item_keys[] = $queue.'_'.$i;
		$null = null;
		
		return $memcached->getMulti($item_keys, $null, Memcached::GET_PRESERVE_ORDER); 
	}
	
	public static function enqueue($queue, $item) {
		$memcached = self::getInstance();
		
		$id = $memcached->increment($queue.'_tail');
		if($id === false) {
			if($memcached->add($queue.'_tail', 1, MQ_TTL) === false) {
				$id = $memcached->increment($queue.'_tail');
				if($id === false) 
					return false;
			} else {
				$id = 1;
				$memcached->add($queue.'_head', $id, MQ_TTL);
			}
		}
		
		if($memcached->add($queue.'_'.$id, $item, MQ_TTL) === false) 
			return false;
		
		return $id;
	}
	
}
