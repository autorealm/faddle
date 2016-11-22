<?php namespace Faddle\Support;

use Exception;

/**
 * 观察者类
 */
class Observer implements \SplSubject, \IteratorAggregate, \Countable {

	/**
	 * 单例对象
	 */
	private static $instance;

	/**
	 * @var SplObjectStorage
	 */
	private $observers;

	/**
	 * @var array
	 */
	public $errors = array();

	/**
	 * 取得单例对象
	 */
	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Singleton : can't be cloned
	 */
	private function __clone() {}

	/**
	 * Singleton constructor
	 */
	private function __construct() {
		$this->observers = new \SplObjectStorage();
	}

	/**
	 * attach observers
	 *
	 * @param \SplObserver $observer
	 * @return $this
	 */
	public function attach(\SplObserver $observer) {
		if (is_null($this->observers)) $this->observers = new \SplObjectStorage();
		$this->observers->attach($observer);
		return $this;
	}

	/**
	 * detach observer
	 *
	 * @param \SplObserver $observer
	 * @return $this
	 */
	public function detach(\SplObserver $observer) {
		if (! empty($this->observers)) {
			$this->observers->detach($observer);
		}
		return $this;
	}

	/**
	 * notify observers
	 * @return int
	 */
	public function notify() {
		if (empty($this->observers)) return;
		$i = 0;
		foreach ($this as $observer) {
			try {
				$observer->update($this);
				$i++;
			} catch(\Exception $e) {
				$this->errors[] = array($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
			}
		}
		return $i;
	}

	/**
	 * IteratorAggregate
	 * @return \Iterator
	 */
	public function getIterator() {
		return $this->observers;
	}

	/**
	 * Countable
	 * @return int
	 */
	public function count() {
		return count($this->observers);
	}

	/**
	 * 
	 * @param string $funct
	 * @param array $args
	 * @throws \BadMethodCallException
	 */
	public function __call($funct, $args) {
		
		throw new \BadMethodCallException("unknown method $funct");
	}

}