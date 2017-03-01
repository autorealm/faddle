<?php namespace Faddle;

use Exception;
use Faddle\App as App;
use Faddle\Http\Request as Request;
use Faddle\Http\Response as Response;

abstract class Controller {
	private static $_instance;
	protected $_behaviors = array();
	public $request;
	public $response;
	
	protected $resolver;
	
	function __construct(callable $resolver=null) {
		self::$_instance = & $this;
		$this->resolver = $resolver;
		$this->request = Request::getInstance();
		$this->response = Response::getInstance();
		if (method_exists($this, 'init')) $this->init();
	}

	public function __call($method, $arguments) {
		if (!method_exists($this->resolver, $method)) {
			throw new \BadMethodCallException(sprintf('Method "%s::%s" does not exist.', get_class($this->resolver), $method));
		}
		return call_user_func_array(array($this->resolver, $method), $arguments);
	}

	public function __invoke() {
		if ($this->trigger()) return call_user_func_array($this->resolver, func_get_args());
		else return false;
	}

	public function __get($name) {
		$getter = 'get' . $name;
		if (method_exists($this, $getter)) {
			return $this->$getter();
		} else {
			if (method_exists($this->resolver, $getter) && property_exists($this->resolver, $name))
				return $this->resolver->$name;
		}
		throw new \Exception('Getting unknown property: ' . get_class($this) . '::' . $name);
	}

	public function __set($name, $value) {
		$setter = 'set' . $name;
		if (method_exists($this, $setter)) {
			return $this->$setter($value);
		} else {
			if (method_exists($this->resolver, $setter) && property_exists($this->resolver, $name))
				return $this->resolver->$setter($value);
		}
		throw new \Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
	}

	public static function & getInstance() {
		if (! self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	public function filter($sub) {
		$this->_behaviors[] = $sub;
	}

	public function trigger() {
		if(!empty($this->_behaviors)) {
			foreach($this->_behaviors as $observer) {
				if (method_exists($observer, 'handle')) $result = $observer->handle();
				elseif (is_callable($observer)) $result = call_user_func($observer);
				if ($result === false) return false;
			}
		}
		return true;
	}

	protected function resolve($entry) {
		if (! $entry) {
			return function (Request $request, Response $response, callable $next) {
				return $response;
			};
		}
		
		if (! $this->resolver) {
			return $entry;
		}
		
		return call_user_func($this->resolver, $entry);
	}

}
