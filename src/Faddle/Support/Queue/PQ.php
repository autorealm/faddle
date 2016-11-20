<?php namespace Faddle\Support\Queue;

use Exception;

class PQ {
	
	private static $queues = array();
	public static $maxsize = 1024;
	
	protected function __construct() {
		
	}
	
	private function __clone() {}
	
	private static function getQueue($k) {
		if (static::exists($k)) return static::$queues[$k];
		$q = msg_get_queue(ftok(__DIR__, chr(rand(65,90))), 0666);
		//msg_set_queue($q, array('msg_perm.uid'=>'80'));
		static::$queues[$k] = $q;
		return $q;
	}
	
	public static function setQueue($k, $q) {
		if (msg_queue_exists($q)) static::$queues[$k] = $q;
	}

	public static function exists($k) {
		return array_key_exists($k, static::$queues) and msg_queue_exists(static::$queues[$k]);
	}

	public static function remove($k) {
		if (! static::exists($k)) return false;
		$q = static::getQueue($k);
		unset(static::$queues[$k]);
		return msg_remove_queue($q);
	}

	public static function enqueue($k, $d) {
		$q = static::getQueue($k);
		return msg_send($q, 1, $d, true);
	}

	public static function dequeue($k) {
		$q = static::getQueue($k);
		//msg_stat_queue($q);
		msg_receive($q, 0, $type, static::$maxsize, $data, true, MSG_IPC_NOWAIT);
		return $data;
	}

}