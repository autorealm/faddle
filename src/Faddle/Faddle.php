<?php namespace Faddle;

use Exception;
use BadMethodCallException;
use InvalidArgumentException;
use ReflectionException;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;

/**
 * Faddle Abstract Class
 * @author KYO
 * @since 2016-01-15
 */
abstract class Faddle {

	protected $objects;
	protected $services = array();
	protected $instances = array();


	public function __construct() {
		$this->objects = new \SplObjectStorage();
		
	}
	
	public function __destruct() {
		
	}
	
	public function __call($method, $args) {
		if (isset($this->services[$method]) and is_callable($this->services[$method])) {
			$return = call_user_func($this->services[$method]);
			//继续判断返回值是否可回调
			if (is_callable($return)) {
				return call_user_func_array($return, $args);
			} elseif ($return instanceof ReflectionFunctionAbstract) {
				return $this->executeCallback($return, $args);
			}
			return $return;
		} else foreach ($this->instances as $instance) {
			if (method_exists($instance, $method)) {
				return call_user_func_array(array($instance, $method), $args);
			}
		}
		throw new BadMethodCallException(sprintf('Unknown method: %s()', $method));
	}
	
	public static function __callStatic($func, $args) {
		$feature = get_called_class();
		// todo coding
	}

	/**
	 * 闭包加载函数方法（调用 run 时开始执行）
	 *
	 * @param callable $closure
	 * @return callable
	 */
	public function share($closure) {
		if (!is_callable($closure)) {
			throw new \InvalidArgumentException('Service definition is not a Closure or callable object.');
		}
		if (is_null($this->objects)) $this->objects = new \SplObjectStorage();
		$shared = function ($container) use ($closure) {
			static $object;
			if (is_null($object)) {
				if (method_exists($closure, '__invoke')) $object = $closure($container);
				else $object = call_user_func($closure, $container);
			}
			return $object;
		};
		
		if ($this->objects->contains($shared)) {
			$this->objects->detach($shared);
		}
		$this->objects->attach($shared);
		
		return $shared;
	}

	public function run() {
		
		if (! empty($this->objects)) {
			$this->objects->rewind();
			while($this->objects->valid()) {
				try {
					call_user_func($this->objects->current(), $this);
				} catch(Exception $e) {
					if (method_exists($this, 'handle')) $this->handle($e);
					else throw $e;
				}
				$this->objects->next();
			}
		}
		
	}

	/**
	 * 附加实例方法
	 */
	public function attach($instance) {
		if ((class_exists($instance) or is_object($instance)) and !in_array($instance, $this->instances))
			$this->instances[] = $instance;
		return $this;
	}

	/**
	 * 扩展服务方法
	 *
	 * @param string $name
	 * @param callable $callable
	 * @return callable
	 */
	public function extend($name, $callable) {
		if (!is_object($this->services[$name]) || !method_exists($this->services[$name], '__invoke')) {
			throw new \InvalidArgumentException(sprintf('Identifier "%s" does not contain an object definition.', $name));
		}
		
		if (!is_object($callable) || !method_exists($callable, '__invoke')) {
			throw new \InvalidArgumentException('Extension service definition is not a Closure or invokable object.');
		}
		
		$factory = $this->services[$name];
		
		$extended = function ($c) use ($callable, $factory) {
			return $callable($factory($c), $c);
		};
		
		$this->services[$name] = $extended;
		
		return $extended;
	}

	/**
	 * 注册服务方法
	 *
	 * @param string $name       服务名称
	 * @param callable $closure  服务回调函数
	 * @return mixed
	 */
	public function register($name, $closure) {
		$this->services[$name] = function () use ($closure) {
			static $instance;
			if (null === $instance) {
				//$instance = new ReflectionFunction($closure);
				$instance = self::genCallableReflection($closure);
			}
			return $instance;
		};
		return $this;
	}

	/**
	 * 绑定一个服务类方法
	 *
	 * @access public
	 * @param  string   $name    服务名称
	 * @param  mixed    $class   一个类或者对象
	 * @param  string   $method  方法名称
	 * @return Server
	 */
	public function bind($name, $class, $method = '') {
		if (empty($method)) $method = $name;
		
		$this->services[$name] = function () use ($class, $method) {
			static $instance;
			if (null === $instance) {
				if (method_exists($class, $method)) {
					$instance = array($class, $method);
				} else {
					//throw new BadFunctionCallException('Unable to find the procedure.');
				}
			}
			return $instance;
		};
		return $this;
	}

	public function executeCallback($callback, $params) {
		if ($callback instanceof ReflectionFunction)
			$reflection = &$callback;
		else $reflection = new ReflectionFunction($callback);
		
		$arguments = $this->getArguments(
			$params,
			$reflection->getParameters(),
			$reflection->getNumberOfRequiredParameters(),
			$reflection->getNumberOfParameters()
		);
		
		return $reflection->invokeArgs($arguments);
	}

	public function executeMethod($class, $method, $params) {
		$instance = is_string($class) ? new $class : $class;
		$reflection = new ReflectionMethod($class, $method);
		
		$arguments = $this->getArguments(
			$params,
			$reflection->getParameters(),
			$reflection->getNumberOfRequiredParameters(),
			$reflection->getNumberOfParameters()
		);
		
		return $reflection->invokeArgs($instance, $arguments);
	}

	/**
	 * Get procedure arguments
	 *
	 * @access public
	 * @param  array    $request_params       Incoming arguments
	 * @param  array    $method_params        Procedure arguments
	 * @param  integer  $nb_required_params   Number of required parameters
	 * @param  integer  $nb_max_params        Maximum number of parameters
	 * @return array
	 */
	public function getArguments(array $request_params, array $method_params, $nb_required_params, $nb_max_params) {
		$nb_params = count($request_params);
		if ($nb_params < $nb_required_params) {
			throw new InvalidArgumentException('Wrong number of arguments');
		}
		if ($nb_params > $nb_max_params) {
			throw new InvalidArgumentException('Too many arguments');
		}
		//true if we have positional parametes
		if (array_keys($request_params) === range(0, count($request_params) - 1)) {
			return $request_params;
		}
		$params = array(); //Get named arguments
		foreach ($method_params as $p) {
			$name = $p->getName();
			if (isset($request_params[$name])) {
				$params[$name] = $request_params[$name];
			}
			else if ($p->isDefaultValueAvailable()) {
				$params[$name] = $p->getDefaultValue();
			}
			else {
				throw new InvalidArgumentException('Missing argument: '.$name);
			}
		}
		
		return $params;
	}

	/**
	 * 
	 * @param callable $callable
	 * @return \ReflectionFunctionAbstract
	 */
	public static function genCallableReflection($callable) {
		// Closure
		if ($callable instanceof \Closure) {
			return new \ReflectionFunction($callable);
		}
		// Array callable
		if (is_array($callable)) {
			list($class, $method) = $callable;
			return new \ReflectionMethod($class, $method);
		}
		// Callable object (i.e. implementing __invoke())
		if (is_object($callable) && method_exists($callable, '__invoke')) {
			return new \ReflectionMethod($callable, '__invoke');
		}
		// Callable class (i.e. implementing __invoke())
		if (is_string($callable) && class_exists($callable) && method_exists($callable, '__invoke')) {
			return new \ReflectionMethod($callable, '__invoke');
		}
		// Standard function
		if (is_string($callable) && function_exists($callable)) {
			return new \ReflectionFunction($callable);
		}
		
		return false;
	}

	public static function getReflectionParameters(ReflectionFunctionAbstract $reflection,
		array $providedParameters, array $resolvedParameters, $flag=0) {
		
		$parameters = $reflection->getParameters();
		// Skip parameters already resolved
		if (! empty($resolvedParameters)) {
			$parameters = array_diff_key($parameters, $resolvedParameters);
		}
		foreach ($parameters as $index => $parameter) {
			if (($flag == 0 || $flag == 1) && array_key_exists($parameter->name, $providedParameters)) {
				$resolvedParameters[$index] = $providedParameters[$parameter->name];
			}
			if (($flag == 0 || $flag == 2) && $parameter->isOptional()) {
				try {
					$resolvedParameters[$index] = $parameter->getDefaultValue();
				} catch (\ReflectionException $e) {
					// Can't get default values from PHP internal classes and functions
				}
			}
			if (($flag == 0 || $flag == 3) && is_int($index)) {
				$resolvedParameters[$index] = $value;
			}
		}
		$diff = array_diff_key($parameters, $resolvedParameters);
		if (empty($diff)) {
			// all parameters are resolved
		}
		// Sort by array key because call_user_func_array ignores numeric keys
		//ksort($resolvedParameters);
		
		return $resolvedParameters;
	}

}


/**
 * Faddle Service Provider Interface
 */
interface ServiceProviderInterface {

	public function register(Faddle $app);

}
