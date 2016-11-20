<?php namespace Faddle\Support\Queue;

use Exception;

class DQ {
	
	private static $q = NULL;
	private $parallelism = 0;
	private $tasks = array();
	private $started = 0; // 已开始的任务数
	private $active = 0; // 当前执行的任务数
	private $remaining = 0; // 剩余的任务数
	private $popping;
	private $error;
	private $await;
	private $all;
	
	public function __construct($parallelism) {
		if (!$parallelism) $parallelism = 256;
		$this->parallelism = $parallelism;
		$this->await = function() {};
		self::$q = $this;
	}
	
	private function __clone() {}
	
	public static function getInstance() {
		if(!self::$q) self::$q = new self();
		return self::$q;
	}

	private function pop() {
		while ($this->popping = ($this->started < count($this->tasks) && $this->active < $this->parallelism)) {
			$i = $this->started++;
			$t = $this->tasks[$i];
			$a = array_slice($t, 1);
			$a[] = $this->callback($i);
			++$this->active;
			call_user_func_array($t[0], $a);
		}
	}

	private function callback($i) {
		$self = &$this;
		return function($e, $r) use ($self, $i) {
			--$self->active;
			if ($self->error != null) return;
			if ($e != null) {
				$self->error = $e; // ignore new tasks and squelch active callbacks
				$self->started = $self->remaining = null; // stop queued tasks from starting
				$self->notify();
			} else {
				$self->tasks[$i] = $r;
				if (--$self->remaining) $self->popping or $self->pop();
				else $self->notify();
			}
		};
	}

	private function notify() {
		if ($this->error != null) call_user_func($this->await, $this->error);
		else if ($this->all) call_user_func($this->await, null, $this->tasks);
		else call_user_func_array($this->await, array_merge(array(null), $this->tasks));
	}

	public function defer() {
		if (!$this->error) {
			$this->tasks[] = func_get_args();
			++$this->remaining;
			$this->pop();
		}
		return $this;
	}
	
	public function await(\Closure $fn, $all=false) {
		$this->await = $fn;
		$this->all = (bool) $all;
		if (!$this->remaining) $this->notify();
		return $this;
	}

}