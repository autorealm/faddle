<?php namespace Faddle;

use Faddle\Router\Router as Router;
use Faddle\Support\ErrorHandler as Handler;
use Faddle\Support\Collection;
use Faddle\Http\Request as Request;
use Faddle\Http\Response as Response;

/**
 * App
 */
class App extends Faddle {
	const VERSION = '1.2.5';
	const VERCODE = 'Dawn';
	private static $_instance;
	private static $loads = array();
	public $config = array();
	public $router ;
	public $loader;
	public $event;
	public $injector;
	public $base_path;

	public function __construct($base_path=false, $config_file=false) {
		parent::__construct();
		register_shutdown_function(array($this, '__shutdown'));
		self::$_instance = $this;
		$this->base_path = $base_path;
		$this->config = new Config($config_file);
		
		$this->init();
	}
	
	public function __destruct() {
		parent::__destruct();
		$this->loader->unregister();
		$this->event->trigger('end'); //应用结束
		unset($this->injector);
	}
	
	private function init() {
		if (!isset($this->loader)) {
			$this->loader = new Loader();
		}
		$this->loader->register();
		$this->loader->finder($this->config('loader.finder'));
		
		if (!isset($this->event)) {
			$this->event = Event::build(['start', 'before', 'obtain', 'present', 'completed', 
				'error', 'notfound', 'badrequest', 'unavailable', 'next', 'end'], null);
		}
		if (!isset($this->router)) {
			$this->router = new Router();
		}
		if (!isset($this->injector)) {
			$this->injector = new Collection();
		}
		
		$this->loader->import($this->config('loader.import', array()));
		$this->error_handler($this->config('error.handler', array('Faddle\Support\ErrorHandler', 'handle')));
		$this->exception_handler($this->config('exception.handler',
			function($exception) {
				$self = static::getInstance();
				$self->handle($exception);
			}
		));
	}

	/**
	 * 获取应用实例
	 * @return Novious
	 */
	public static function instance() {
		if (! self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public static function getInstance() {
		return static::instance();
	}
	
	public static function load($file) {
		if (! is_file($file)) {
			throw new \InvalidArgumentException('Cant load non-exists file "' . $file . '"');
		}
		if (isset(self::$loads[$file])) {
			return self::$loads[$file];
		}
		return self::$loads[$file] = include($file);
	}

	public function call($method, $args) {
		if ($this->injector->has($method)) {
			$obj = $this->injector->get($method);
			if (is_callable($obj)) {
				return call_user_func_array($obj, $args);
			}
		}
	}

	/**
	 * 设置路由器
	 * @param Router $router
	 */
	public function router($router) {
		if (isset($router) and ($router instanceof Router))
			$this->router = $router;
		return $this->router;
	}
	
	/**
	 * 注册蓝图，分配子路由器
	 * @param unknown $pattern 路径适配表达式
	 * @param Router $router
	 */
	public function register_blueprint($pattern, $router) {
		$this->router->group($pattern, $router);
		return $this;
	}
	
	/**
	 * 路由
	 * @param string $pattern 路径适配表达式
	 * @param mixed $callback 回调函数或数组
	 * @param array $method 请求方法
	 * @return Novious
	 */
	public function route($method, $pattern, $callback) {
		$this->router->route($pattern, $callback, $method);
		return $this;
	}

	/**
	 * 记录信息
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	public static function mark($key, $value) {
		static $mark;
		if (! isset($mark)) $mark = array();
		if (isset($key)) {
			if (isset($value)) $mark[$key] = $value;
			else return $mark[$key];
		}
		
		return $mark;
	}
	
	/**
	 * 配置数据
	 * @param array|string $config
	 * @return mixed
	 */
	public function config($config, $default_value=null) {
		if (! empty($config)) {
			$app_config = &$this->config;
			if (is_array($config)) {
				foreach ($config as $k => $v) {
					$app_config[$k] = $v;
				}
			} elseif (is_string($config)) { //区分数组与 Config 对象
				if (is_object($app_config)) return $app_config->get($config, $default_value);
				else return array_key_exists($config, $app_config) ? $app_config[$config] : $default_value;
			}
		}
		return $this->config;
	}

	/**
	 * 应用的全局数据
	 */
	public function g($data=null, $odd=null) {
		if (is_array($data)) $this->injector->setData($data);
		elseif (is_string($data)) {
			if (isset($odd)) $this->injector[$data] = $odd;
			else return $this->injector[$data];
		}
		return $this->injector->getData();
	}

	public function injector($data, &$odd) {
		$this->injector[$data] = $odd;
		return $this->injector;
	}

	public function __get($name) {
		return (isset($this->injector[$name])) ? $this->injector[$name] : null;
	}
	
	public function __set($name, $value) {
		$this->injector[$name] = $value;
	}
	
	public function __isset($name) {
		return isset($this->injector[$name]);
	}
	
	public function __unset($name) {
		unset($this->injector[$name]);
	}

	/**
	 * 返回应用错误信息列表
	 * @return array
	 */
	public static function errors() {
		return Handler::errors();
	}
	
	/**
	 * 返回最近一个应用错误信息
	 * @return array
	 */
	public static function get_last_error() {
		return Handler::get_last_error();
	}

	public function error_handler($handler) {
		if (! is_callable($handler)) {
			restore_error_handler();
		} else {
			set_error_handler($handler);
		}
	}
	
	public function exception_handler($handler) {
		if (! is_callable($handler)) {
			restore_exception_handler();
		} else {
			set_exception_handler($handler);
		}
	
	}
	
	/**
	 * 处理错误异常
	 */
	public function handle($err) {
		
		//if ($err instanceof \Exception or $err instanceof \Error)
		if (is_null($err)) $err = error_get_last();
		$this->event->trigger('error', $err);
		
		return $this;
	}

	/**
	 * 监听应用事件
	 */
	public function on($event, $callback) {
		$this->event->on($event, $callback);
		return $this;
	}
	/**
	 * 触发事件
	 */
	public function emit($event) {
		return call_user_func_array(array($this->event, 'fire'), func_get_args());
	}

	//路由事件触发函数

	/**
	 * 应用执行某路由前，可能多次发生
	 * @param Router $rou 路由器
	 */
	public function _beforeRoute($rou=null) {
		$this->event->trigger('before', $rou);
	}
	
	/**
	 * 应用请求开始分发给下一个中间件...
	 * @param mixed $tar 中间件...
	 */
	public function _next($tar=null) {
		$this->event->trigger('next', $tar);
	}
	
	/**
	 * 应用路由已匹配请求
	 * @param Router $rou 路由器
	 */
	public function _obtain($rou=null) {
		$this->event->trigger('obtain', $rou);
	}
	
	/**
	 * 应用开始回应请求
	 * @param string $content 视图文本
	 */
	public function _present(&$content=null) {
		$this->event->trigger('present', $content,
			function($result) use(&$content) {
				if (is_null($result) or $result === false) return;
				else {
					return $content = $result; //持续传递修改后的内容
				}
		});
	}
	
	/**
	 * 应用已完成回应
	 */
	public function _completed() {
		$this->event->trigger('completed');
	}
	
	/**
	 * 应用发生404错误
	 */
	public function _notfound() {
		$this->event->trigger('notfound');
	}
	
	/**
	 * 应用发生400错误
	 */
	public function _badrequest() {
		$this->event->trigger('badrequest');
	}
	
	/**
	 * 应用发生503错误
	 */
	public function _unavailable() {
		$this->event->trigger('unavailable');
	}
	

	//-----------------------------

	/**
	 * 运行应用
	 * @access	  public
	 */
	public function run() {
		$this->event->trigger('start'); //应用开始
		parent::run();
		$this->router->execute($this);
	}

	/**
	 * 中断并结束应用
	 * @param unknown $code
	 * @param string $msg
	 */
	public function abort($msg=null, $code=0) {
		//
		$this->event->trigger('completed', array('code' => $code, 'message' => $msg));
		exit;
	}

	public function __invoke() {
		$this->run();
	}
	
	public function __shutdown() {
		$last_error = error_get_last();
		if (! is_null($last_error)) {
			//错误处理
			if (in_array($last_error['type'], array(E_ERROR, E_WARNING))) {
				//$this->handle($last_error);
			}
			//通知及自定义的错误
			elseif (in_array($last_error['type'], array(E_NOTICE, E_DEPRECATED, 
				E_USER_NOTICE, E_USER_DEPRECATED, E_USER_WARNING, E_USER_ERROR))) {
				//return;
			}
			
		}
	}
	
}