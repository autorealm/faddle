<?php namespace Faddle\Router;

use Exception;
use Faddle\App as App;
use Faddle\Http\Request as Request;
use Faddle\Http\Response as Response;

/**
 * 路由器类
 */
class Router {
	public $name = null; //路由器名称
	public $routes = array(); //路由数组
	public $blueprints = array(); //蓝图
	public $domain = false; //路由器域名
	public $base_path = ''; //路由器基本路径
	public $default_route = null; //默认路由
	protected $middlewares = array(); //路由器中间件数组
	protected $params = array(); //匹配的参数
	protected $callback = null; //匹配的回调
	protected $uses = ''; //匹配的命名空间
	protected $matched_name; //匹配的路由名称
	private static $app;
	private static $query_path = '';
	
	protected static $match_types = array(
			'(\d+)' => array('{:i}', '{:int}', '{:id}', '{:num}'),
			'([0-9A-Za-z\.\-\_]+)'  => array('{:a}', '{:str}', '{:value}', '{:field}'),
			'([0-9A-Fa-f]++)'  => array('{:h}', '{:hex}'),
			'(\w+\d++)' => array('{:v}', '{:xid}', '{:sid}'),
			'([^/]+)' => array('{:any}', '{}'),
			'(.*?)' => array('{:all}')
		);

	/**
	 * 路由器构造函数
	 * @return Router
	 */
	public function __construct($base_path='', $domain=null, $default_route=null) {
		if ($base_path) $this->base_path = trim($base_path);
		if ($domain) $this->domain = $domain;
		if (isset($default_route)) $this->default_route = $default_route;
		if (empty(self::$query_path)) {
			self::$query_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		}
		
	}

	private function __clone() {
		//防止外部克隆对象
	}

	/**
	 * 分配路由
	 */
	public function map(array $routes) {
		foreach ($routes as $path => $callback) {
			if (is_string($path))
				$this->route($path, $callback);
			else
				$this->set($callback);
		}
		return $this;
	}
	
	/**
	 * 设置默认路由
	 */
	public function otherwise($route) {
		$this->default_route = $route;
	}
	
	public function __call($method, $args) {
		if (in_array($method, array('get','post','put','delete', 'gest', 'gets', 'pget', 'rest'))) {
			$method = strtoupper($method);
			if ($method == 'GETS' or $method == 'GEST') $method = ['GET', 'POST'];
			elseif ($method == 'PGET' or $method == 'REST') $method = ['POST', 'PUT', 'GET', 'DELETE'];
			else $method = [$method];
			if (count($args) < 2) {
				trigger_error(sprintf('参数错误(count eq 2)，当前：%s', count($args)), E_USER_WARNING);
				return false;
			}
			$pattern = $args[0];
			$callback = $args[1];
			if (is_array($callback)) {
				$callback['method'] = $method;
			} else {
				$callback = array(
					'controller' => $callback,
					'method' => $method
				);
			}
			$route = new Route($pattern, $callback);
			$this->set($route);
			return $route;
		}
		
		return $this;
	}
	
	public function __invoke() {
		$this->execute();
	}

	/**
	 * 设置路由或路由集
	 * @param Route|Routes $route 路由
	 */
	public function set($route) {
		if ($route instanceof Route) {
			$this->routes[$route->getRegex()] = $route;
		} elseif ($route instanceof Routes) {
			foreach ($route as $r) {
				$this->routes[$r->getRegex()] = $r;
			}
		}
		return $this;
	}
	
	/**
	 * 设置路由
	 * @param string $pattern 路径，匹配路由规则的表达式
	 * @param Closure|array $callback 回调信息
	 * @param string|array $method 请求方法
	 * @return Router
	 */
	public function route($pattern, $callback, $method=null) {
		if (! is_string($pattern)) return $this;
		$pattern = str_replace(array('//', '(', ')'), array('/', '\(', '\)'), $pattern); //替换正则字符
		foreach (static::$match_types as $to => $from) {
			$pattern = str_replace($from, $to, $pattern);
		}
		
		if (! is_null($method)) {
			if (!is_array($method)) $method = (array) $method;
		} else {
			$method = ['POST', 'GET'];
		}
		if (! is_array($callback)) {
			$callback = array(
				'as' => null,
				'controller' => $callback,
				'middleware' => [],
				'method' => $method
			);
		} else {
			//$config = $callback;
		}
		
		$this->routes[$pattern] = $callback;
		return $this;
	}
	
	/**
	 * 组合路由器到路由蓝点
	 * @param string $pattern
	 * @param Router $router
	 */
	public function group($pattern, Router $router) {
		if ($router instanceof Router) {
			$this->blueprints[$pattern][] = $router;
		}
		return $this;
	}
	
	/**
	 * 开始路由功能
	 * @param Faddle $app
	 */
	public function execute($app=null) {
		if ($app) self::$app = $app;
		if (! self::$app instanceof \Faddle\Faddle) {
			trigger_error(sprintf('应用类型错误：%s', gettype($app)), E_USER_WARNING);
			self::$app = App::instance();
		}
		//在本路由开始之前，通知应用触发本事件。
		self::before_route($this);
		//先经过本路由器中间件
		$this->handle_middlewares();
		
		$matched = $this->match();
		if ($matched < 0) {
			//已匹配并转发，未被本路由执行。
			return;
		}
		if ($matched === 1) {
			$this->dispatch();
		} elseif ($matched === 2) { //需要中断分配路由，由中间件或其他请求方式处理。
			self::present(Response::instance()->body());
			Response::instance()->send();
		}
		
		//已回应请求，路由完成。
		self::completed();
		
	}

	/**
	 * 适配路由
	 */
	protected function match($uri=null) {
		//$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		if (! isset($uri)) $uri = self::$query_path;
		//if (($strpos = strpos($uri, '?')) !== false) $uri = substr($uri, 0, $strpos);
		$uri = '/' . ltrim($uri, '/');
		foreach ($this->blueprints as $pattern => $routers) { //找到路由蓝点中的匹配根路径的路由
			$pattern = '/' . ltrim($pattern, '/');
			foreach ((array) $routers as $router ) {
			if (stripos($uri, $pattern) === 0 and $router->checkdomain()) {
				self::$query_path = substr(self::$query_path, strlen($pattern));
				$router->execute();
				return -1;
			}
			}
		}
		$check = false;
		if (! empty($this->base_path)) {
			if ((strpos($uri, $this->base_path)) === 0) {
				$_uri = substr($uri, strlen($this->base_path));
				if (empty($_uri) or strpos($_uri, '/') === 0) {
					$uri = $_uri;
					$check = $this->checkdomain();
				}
			}
		} else $check = $this->checkdomain();
		if (! $check) { //域名不匹配本路由则404
			self::notfound();
			return 0;
		}
		
		foreach ($this->routes as $pattern => $callback) {
			$pattern = '/^(' . str_replace('/', '\/', $pattern) . ')(\?[^#]*)?(#.*)?$/';
			if (preg_match($pattern, $uri, $params)) {
				unset($params[0]);
				array_shift($params);
				$params = array_map('rawurldecode', $params);
				$this->params = $params;
				
				return $this->load_callback($callback);
			}
		}
		if (isset($this->default_route)) {
			return $this->load_callback($this->default_route);
		} else {
			self::notfound();
			return 0;
		}
	}

	/**
	 * 载入回调
	 */
	private function load_callback($callback) {
		$middleware = [];
		$request_method = isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
							? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : $_SERVER['REQUEST_METHOD'];
		if ($callback instanceof Route) {
			$route = $callback;
			$uses = $route->uses;
			$name = $route->name;
			$middleware = $route->middlewares;
			if (! $route->matchMethod() and $request_method != 'OPTIONS') {
				self::badrequest();
				return 0;
			}
		} elseif (is_callable($callback)) {
			$this->callback = $callback;
			return 1;
		} elseif (is_array($callback)) {
			$method = array_key_exists('method', $callback) ? $callback['method'] : 'GET';
			$uses = array_key_exists('use', $callback) ? $callback['use'] : '';
			$name = array_key_exists('as', $callback) ? $callback['as'] : null;
			$middleware = array_key_exists('middleware', $callback) ? $callback['middleware'] : [];
			$callback = array_key_exists('controller', $callback) ? $callback['controller'] : null;
			if (! in_array($request_method, (array) $method) and $request_method != 'OPTIONS') {
				self::badrequest();
				return 0;
			}
		} else {
			self::badrequest();
			return 0;
		}
		
		$this->middlewares = array_merge($this->middlewares, (array)$middleware);
		$this->uses = $uses;
		$this->matched_name = $name;
		$this->callback = $callback;
		
		if ($request_method == 'OPTIONS' or Response::getInstance()->prepared) {
			return 2;
		}
		
		return 1;
	}
	
	/**
	 * 获取匹配的路由
	 * @return array
	 */
	public function getMatched() {
		$params = $this->params;
		if ($this->callback instanceof Route) {
			$params = array_merge($this->callback->params(), $params);
		}
		return array (
			'middleware' => $this->middlewares,
			'uses' => $this->uses,
			'name' => $this->matched_name,
			'params' => $params,
			'callback' => $this->callback
		);
	}
	
	/**
	 * 路由分发
	 */
	protected function dispatch() {
		$data = (array) self::parse();
		$data['args'] = $this->params;
		Request::data($data); //设置本次请求的参数
		
		self::obtain($this); //已匹配
		
		if ($this->callback instanceof Route) {
			$this->callback->emitBefore();
		}
		
		//调用中间件
		$this->handle_middlewares();
		
		$result = false;
		if (empty($this->callback)) {
			//
		} else if (Response::instance()->prepared) {
			$result = true;
		} else if ($this->callback instanceof Route) {
			$route = $this->callback;
			$callback = $route->callback();
			try {
				$result = call_user_func($route, ($this->params));
			} catch (Exception $e) {
				if (self::$app != null) self::$app->handle($e);
				trigger_error(sprintf('路由调用出现异常：%s', $e->getMessage()), E_USER_WARNING);
				$result = false;
			}
			$route->emitAfter();
		} else if ($this->callback instanceof \Closure) {
			$result = call_user_func_array($this->callback, array_values($this->params));
		} else if (is_array($this->callback)) {
			$result = call_user_func_array($this->callback, array_values($this->params));
		} else {
			if ($callback = $this->parse_callback()) {
				$result = call_user_func_array($callback, array_values($this->params));
			} else {
				trigger_error(sprintf('控制器调用出错：%s', strval($this->callback)), E_USER_WARNING);
				$result = false;
			}
		}
		
		if ($result === false) self::error();
		else if ($result === true) { //已处理
			self::present(Response::instance()->body());
			Response::instance()->send();
		} else if ($result === null) { //已回应
			
		} else {
			self::present($result);//将回应
			if (! empty($result))
				Response::instance()->display($result);
		}
		
		return ($result === false) ? false : true;
	}

	/**
	 * 解析回调函数
	 */
	private function parse_callback() {
		$callback = (string) $this->callback;
		$calls = explode('@', $callback);
		if (count($calls) < 2) $calls = explode('::', $callback);
		if (count($calls) < 2) {
			if ($this->params[0]) {
				$calls[1] = $this->params[0];
				unset($this->params[0]);
			} else {
				$calls[1] = '__invoke';
			}
		}
		$fun_method = $calls[1];
		try {
			$calls[0] = $this->uses . '\\' . $calls[0];
			if (class_exists($calls[0])) $controller = new $calls[0]();
		} catch (Exception $e) {
			//print $e->getMessage();
		}
		if ($controller and method_exists($controller, $fun_method))
			return array($controller, $fun_method);
		else
			return false;
	}

	/**
	 * 处理中间件
	 */
	private function handle_middlewares() {
		
		foreach ($this->middlewares as $k => $mw) {
			if (empty($mw)) continue;
			if (! is_int($k)) {
				if ($k !== $this->matched_name and !stristr(Request::path(), $k) and 
					! preg_match($k, Request::path())) {
						continue;
				}
			}
			self::next($mw); //中间件
			if (is_string($mw)) {
				if (class_exists($mw)) {
					$mw = new $mw();
				} else {
					$mw = $this->uses . '\\' . $mw;
					if (class_exists($mw)) $mw = new $mw();
				}
			} else if (is_array($mw) and is_string($mw[0])) {
				if (class_exists($mw[0])) {
					$mw = self::instObject($mw[0], array_splice($mw, 1));
				} else {
					$_mw = $this->uses . '\\' . $mw[0];
					if (class_exists($_mw))
						$mw = self::instObject($_mw, array_splice($mw, 1));
				}
			}
			
			if (is_array($mw) and is_callable($mw[0])) {
				$result = call_user_func_array($mw[0], array_splice($mw, 1));
			} else if (method_exists($mw, 'handle')) {
				$result = $mw->handle($this);
			} else if (is_callable($mw)) {
				$result = call_user_func($mw, Request::getInstance(), Response::getInstance());
			} else {
				trigger_error(sprintf('中间件被忽略：%s@%s', $k, (string)$mv), E_USER_WARNING);
			}
			
			if ($result instanceof Response and $result != Response::getInstance()) {
				$result->prepared = true;
				Response::setInstance($result); //使该回应得到处理
				break; //中断中间件
			}
			//防止重复调用
			unset($this->middlewares[$k]);
		}
		//重置中间件
		$this->middlewares = array();
	}

	/**
	 * 检查路由是否匹配指定的域名
	 *
	 * @param string $domain Set Domain
	 * @return array|boolean arguments
	 */
	private function checkdomain($domain=null) {
		if (!isset($domain)) $domain = $this->domain;
		if (!empty($domain)) {
			$server = $_SERVER['HTTP_HOST'] ?: $_SERVER['SERVER_NAME'];
			if (is_array($domain)) {
				foreach ($domain as $d) {
					if (strtolower($d) == strtolower($server)) return true;
				}
			} else {
				$domain = (string)$domain;
				if (preg_match($domain, $server, $arguments)) {
						return $arguments;
				}
				return stristr($server, $domain);
			}
			return false;
		}
		return true;
	}

	/**
	 * 设置并返回路由中间件
	 * @param string|array $middlewares
	 * @return mixed
	 */
	public function middlewares($middlewares) {
		if (is_string($middlewares)) $middlewares = explode('|', $middlewares);
		if (! empty($middlewares)) {
			foreach ((array) $middlewares as $pattern => $middleware) {
				if (is_int($pattern)) $this->middleware($middleware);
				else $this->attach($pattern, $middleware);
			}
		}
		return $this->middlewares;
	}
	public function middleware($middleware) {
		if (func_num_args() > 0) {
			$middlewares = (array) func_get_args();
			foreach ($middlewares as $middleware) {
				if (!is_null($middleware) and !in_array($middleware, $this->middlewares)) {
					$this->middlewares[] = $middleware;
					//array_unshift($this->middlewares, $middleware);
				}
			}
		}
		return $this;
	}
	
	/**
	 * 设置路由中间件
	 * @param string $pattern 需要匹配的路径
	 * @param mixed $middleware
	 * @return Router
	 */
	public function attach($pattern, $middleware) {
		if (isset($middleware)) {
			$this->middlewares[$pattern] = $middleware;
		}
		return $this;
	}

	/**
	 * 取得某路由回调名称的对应URI或者回调本体。
	 * @param string $name 回调名称，就是回调本体的[as]参数。
	 * @param string $back 是否返回本体
	 * @param string $prefix 一般不用设置
	 * @return string|array|boolean
	 */
	public function line($name, $back=false, $prefix='') {
		
		foreach ($this->routes as $pattern => $callback) {
			$pattern = '/' . trim($pattern, '/');
			$as = array();
			if (is_array($callback)) {
				$as = array_key_exists('as', $callback) ? $callback['as'] : null;
			} elseif ($callback instanceof Route) {
				$as = $route->uses();
			}
			if (in_array($name, (array) $as)) {
				if ($back) return $callback;
				return $prefix . $pattern;
			}
		}
		
		foreach ($this->blueprints as $pattern => $router) {
			$pattern = '/' . trim($pattern, '/');
			$_prefix = $prefix . $pattern;
			$ret = $router->line($name, $back, $_prefix);
			if ($ret) return $ret;
		}
		
		return false;
	}
	
	/**
	 * 解析URL地址
	 * @param string $url
	 * @return multitype:string mixed multitype:unknown
	 */
	public static function parse($url=null) {
		$request_uri = $_SERVER['REQUEST_URI'];
		$query_string = $_SERVER['QUERY_STRING'];
		if (!isset($url) or $url == null)
			$url = $request_uri;
		$url_query = parse_url($url);
		$path = $url_query['path'];
		$query = (isset($url_query['query']) ? ''.$url_query['query'] : '');
		$fragment = (isset($url_query['fragment']) ? ''.($url_query['fragment']) : '');
		$params = array();
		
		$arr = (!empty($query)) ? explode('&', $query) : array();
		if (count($arr) > 0) {
			foreach ($arr as $a) {
				$tmp = explode('=', $a);
				if (count($tmp) == 2) {
					$params[$tmp[0]] = $tmp[1];
				}
			}
		}
		
		return array (
			'path' => $path,
			'params' => $params,
			'fragment' => $fragment
		);
	}

	/**
	 * 获取带指定参数匹配路径
	 *
	 * @param array $args	参数数组
	 * @return string
	 */
	public static function path($url, array $args=array()) {
		// replace route url with given parameters
		if ($args && preg_match_all("/:(\w+)/", $url, $param_keys)) {
			// grab array with matches
			$param_keys = $param_keys[1];
			// loop trough parameter names, store matching value in $params array
			foreach ($param_keys as $key) {
				if (isset($args[$key])) {
					$url = preg_replace("/:(\w+)/", $args[$key], $url, 1);
				}
			}
		}
		return $url;
	}
	
	public static function instObject($class, array $params = array()) {
		if (! class_exists($class)) return null;
		switch (count($params)) {
			case 0:
				return new $class();
			case 1:
				return new $class($params[0]);
			case 2:
				return new $class($params[0], $params[1]);
			case 3:
				return new $class($params[0], $params[1], $params[2]);
			case 4:
			case 5:
			case 6:
				return new $class($params[0], $params[1], $params[2], $params[3], 
					isset($params[4]) ? $params[4] : null, isset($params[5]) ? $params[5] : null);
			default:
				return null;
		}
	}

	//=================================== Events ===================================//
	
	protected static function obtain($who) {
		 //通知应用请求已匹配本路由
		if (self::$app != null) self::$app->_obtain($who);
	}
	
	protected static function next($mw) {
		//通知应用请求正在通过中间件处理。
		if (self::$app != null) self::$app->_next($mw); 
	}
	
	protected static function present(&$content) {
		//通知应用将要回应的文本内容。
		if (self::$app != null) self::$app->_present($content); 
	}
	
	protected static function before_route($who) {
		//将本路由作为参数，通知应用本路由将开始。
		if (self::$app != null) self::$app->_beforeRoute($who);
	}
	
	protected static function completed() {
		if (self::$app != null) self::$app->_completed();
	}
	
	protected static function notfound() {
		if (! headers_sent($file, $line)) {
			http_response_code(404);
		}
		if (self::$app != null) self::$app->_notfound();
	}
	
	protected static function badrequest() {
		if (! headers_sent($file, $line)) {
			http_response_code(400);
		}
		if (self::$app != null) self::$app->_badrequest();
	}
	
	protected static function error() {
		if (! headers_sent($file, $line)) {
			http_response_code(503);
		}
		if (self::$app != null) self::$app->_unavailable();
	}
	
	protected static function redirect($path) {
		@header('Location: '.$path, true, 302);
		//http_response_code(302);
	}

}
