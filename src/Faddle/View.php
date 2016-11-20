<?php namespace Faddle;

use Faddle\View\ViewEngine as ViewEngine;

/**
 * 视图类
 * @author KYO
 * @since 2015-9-21
 */
class View {
	public static $default_extension = '.html';
	protected static $config = array();
	public static $extends = array();
	private $data = array();
	private $path;
	private $engine;
	private $engine_config = array();
	public $cache = false;
	
	public function __construct($config=array()) {
		if (empty(self::$config)) { //初始配置
			self::$config = array();
			self::$config['default'] = array( //默认 faddle 视图引擎的配置（key 可随意）
					'suffix' => ['.view.php', '.faddle.php'], //模板文件后缀名
					'template_path' => '/views', //模板文件夹位置
					'engine' => 'default', //模板引擎名称（默认 defalut/faddle/view ，区别 $extends 的 key）
					'static_cache' => false, //是否开启静态缓存（开启后将缓存经解析的静态内容）
					'storage_path' => 'views', //静态缓存输出路径
					'storage_expires' => 3600, //静态缓存过期时间（秒）
					'cache' => false, //是否开启动态缓存
					'cache_path' => 'views', //动态缓存路径（或作为 cache_driver 的 key 前缀）
					'cache_driver' => null, //缓存驱动器对象（未指定则使用文件缓存）
					'cache_expires' => 7200, //动态缓存过期时间（秒）
			);
			self::$config['html'] = array(
					'suffix' => ['.html', '.txt'],
					'template_path' => '/html',
					'engine' => 'html', //名称 html/text，模板将作为静态文件直接读取
			);
			self::$config['nature'] = array(
					'suffix' => '.php',
					'template_path' => '/views',
					'static_cache' => false,
					'storage_path' => 'cache',
					'engine' => '', //名称未识别将采用原生PHP模板解析
					'expire' => 3600,
			);
		}
		$config = (array) $config;
		$isdis = false; //判断是否是二维数组
		foreach($config as $c) {
			if (is_array($c)) $isdis = true;
			break;
		}
		if (! $isdis) $_config['engine'] = $config;
		else $_config = $config;
		self::$config = array_merge(self::$config, $_config);
	}
	
	protected function init_engine($engine) {
		if (! is_array($engine)) {
			$this->engine = null;
			return;
		}
		$this->engine_config = $engine;
		
		if ($engine['engine']) {
			$this->engine = strtolower($engine['engine']);
		} else {
			$this->engine = null;
		}
	}
	
	public function __get($name) {
		return (array_key_exists($name, $this->data)) ? $this->data[$name] : null;
	}
	
	public function __set($name, $value) {
		$this->data[$name] = $value;
	}
	
	public function __isset($name) {
		return isset($this->data[$name]);
	}
	
	/**
	 * 注入模板数据
	 */
	public function assign($key, $value=null) {
		if (is_array($key) or ($key instanceof \Traversable)) {
			foreach ($key as $k => $v) {
				$this->data[$k] = $v;
			}
		} else {
			$this->data[$key] = $value;
		}
		return $this;
	}
	
	/**
	 * 建立并返回视图内容
	 */
	public static function make($template, $data=array(), $config=array()) {
		$view = new self($config);
		
		return $view->show($template, $data);
	}
	
	/**
	 * 解析视图模板并返回内容
	 * @param $template string 模板名称
	 * @param $data array 模板数据
	 */
	public function show($template, $data=array()) {
		list($engine, $file) = $this->path($template, false);
		if ($engine) $engine = self::$config[$engine];
		else $engine = null;
		$this->init_engine($engine);
		
		$this->data = array_merge($this->data, (array)$data);
		$data = $this->data;
		$this->path = $file;
		
		if ($this->path == false) {
			if (starts_with($template, ['http:', 'https:'])) {
				$this->path = $template;
				return @file_get_contents($template);
			}
			
			trigger_error(sprintf('模板加载出错：%s', $template), E_USER_WARNING);
			return false;
		}
		
		$cache = (array_key_exists('static_cache', $this->engine_config)) 
			? $this->engine_config['static_cache'] : $this->cache;
		if ($cache) {
			$compiled_path = $this->compiled();
			if ($this->path and ! $this->expired()) {
				$output = @file_get_contents($compiled_path);
				if (! empty($output)) return $output;
			}
		}
		
		if (in_array($this->engine, array('default', 'faddle', 'view'))) {
			$view = ViewEngine::make( $this->path, $data );
			$view->config($engine);
			
			$output = $view->render();
			
		} else if (in_array($this->engine, array('text', 'html'))) {
			if (is_file($this->path)) {
				$output = @file_get_contents($this->path);
			} else {
				$output = false;
			}
		} else if (array_key_exists(strtolower($this->engine), self::$extends)) {
			$func = self::$extends[strtolower($this->engine)];
			try {
				$output = call_user_func_array($func, array($this->path, $data));
			} catch(\Exception $e) {
				$output = false;
			}
		} else {
			ob_start() and ob_clean();
			
			include ($this->path);
			
			$output = ob_get_clean();
		}
		
		if ($output and is_string($output)) {
			$output = trim($output);
			if ($cache) file_put_contents($compiled_path, $output);
		}
		
		return $output;
	}
	
	/**
	 * 输出视图内容
	 */
	public function display($template, $date=array()) {
		$content = $this->show($template, $date);
		if ($content === false) {
			return false;
		} else {
			@ob_start();
			echo $content;
			@ob_flush();
			return true;
		}
	}
	
	protected function path($path, $rte=true) {
		$_path = trim($path, DIRECTORY_SEPARATOR);
		if (strpos($_path, 'path: ') == 0) {
			$file = substr($_path, 6);
			if (file_exists($file)) {
				if ($rte) return $file;
				else return ['default', $file];
			}
		}
		foreach (self::$config as $name => $config) {
			$suffix = (array_key_exists('suffix', $config)) ? $config['suffix'] : '';
			$template_path = (array_key_exists('template_path', $config)) ? $config['template_path'] : '';
			$suffixs = (array) $suffix;
			foreach ($suffixs as $suffix) {
				$ext = pathinfo($_path, PATHINFO_EXTENSION);
				if (! empty($ext)) {
					$ext = '.'.$ext;
					if (starts_with($suffix, $ext))
						$suffix = str_ireplace($ext, '', $suffix);
				}
				$file = $template_path . DIRECTORY_SEPARATOR . $_path . $suffix;
				if (file_exists($file)) {
					if ($rte) return $file;
					else return [$name, $file];
				}
			}
		}
		
		return false;
	}
	
	protected function compiled($path) {
		$storage_path = (array_key_exists('storage_path', $this->engine_config)) 
			? $this->engine_config['storage_path'] : 'views';
		if (! empty($path))
			return $storage_path.'/'.md5($path).self::$default_extension;
		else
			return $storage_path.'/'.md5($_SERVER['REQUEST_URI']).self::$default_extension;
	}
	
	protected function expired($path) {
		if (array_key_exists('storage_expires', $this->engine_config)) 
			$expire = $this->engine_config['storage_expires'];
		elseif (array_key_exists('expire', $this->engine_config)) 
			$expire = $this->engine_config['expire'];
		$expire = intval($expire);
		$time = filemtime($this->compiled($path));
		
		if ($expire > 0) {
			if (! $time) return true;
			if ($time + $expire < time()) return true;
			else if (empty($path)) return false;
		}
		if (empty($path)) {
			if ($expire <= 0) return false;
			else return true;
		}
		$ftime = filemtime($path);
		
		return $ftime > $time;
	}
	
	/**
	 * 扩展视图方法
	 * @param $name 视图引擎名称，作为唯一标识。
	 * @param $func 视图函数，将传入[模板名称]和[数据]，需返回解析后的内容。
	 */
	public static function extend($name, \Closure $func) {
		if (empty($name)) return false;
		if (!is_callable($func)) return false;
		$name = strtolower($name);
		self::$extends[$name] = $func;
		return true;
	}
	
}
