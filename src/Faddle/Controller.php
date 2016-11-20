<?php namespace Faddle;

use Exception;
use Faddle\App as App;
use Faddle\Http\Request as Request;
use Faddle\Http\Response as Response;

abstract class Controller {
	private static $_instance;
	protected $_middlewares = array();
	public $request;
	public $respone;
	
	protected $resolver;
	
	function __construct(callable $resolver=null) {
		self::$_instance = & $this;
		$this->resolver = $resolver;
		$this->request = Request::getInstance();
		$this->response = Response::getInstance();
		
	}

	public function __call($method, $arguments) {
		if (!method_exists($this->resolver, $method)) {
			throw new \BadMethodCallException(sprintf('Method "%s::%s" does not exist.', get_class($this->resolver), $method));
		}
		return call_user_func_array(array($this->resolver, $method), $arguments);
	}

	public static function & getInstance() {
		if (! self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}

	public function register($sub) {
		$this->_middlewares[] = $sub;
	}

	public function trigger() {
		if(!empty($this->_middlewares)) {
			foreach($this->_middlewares as $observer) {
				if (method_exists($observer, 'handle')) $observer->handle();
				elseif (is_callable($observer)) call_user_func($observer);
			}
		}
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
