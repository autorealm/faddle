<?php namespace Faddle\View;

use Faddle\Helper\TextUtils as TextUtils;
use Faddle\Helper\OneCacheHelper as CacheHelper;

/**
 * 模板引擎类
 * @author KYO
 * @since 2015-9-21
 */
class ViewEngine implements \IteratorAggregate {
	public static $extends = array();
	public static $modifiers = array();
	public static $traces = array();
	public static $first_engine;
	protected static $after_callbacks = array();
	protected static $before_callbacks = array();
	protected static $_macros = array();
	protected static $_extras = array();
	
	private $_vars = array();
	private $_config = array();
	
	private $file;
	private $path = '';
	private $cache = false;
	private $cache_path = '';
	private $cache_file = '';
	private $cacher;
	private $expire = 0;
	
	
	public function __construct($config=array()) {
		$this->_vars = new \ArrayObject(array(), \ArrayObject::ARRAY_AS_PROPS);
		
		if(!isset($config) && defined('ROOT_PATH') && file_exists(ROOT_PATH.'/config/templates.xml')){
			//获取系统变量
			$_sxe = simplexml_load_file(ROOT_PATH.'/config/templates.xml');
			$_tags = $_sxe->xpath('/root/taglib');
			foreach ($_tags as $_tag) {
				$this->_config["{$_tag->name}"] = $_tag->value;
			}
		} else {
			//$this->_config = $config;
			$this->_config = $config + $this->_config;
		}
		if (empty(self::$modifiers)) $this->init_modifier();
		if (empty(self::$extends)) {
			ViewTemplate::helper('elapsed_time', function() {
				return mstimer(1);
			});
			ViewTemplate::helper('memory_usage', function() {
				return round(memory_get_usage() / 1024 / 1024, 3).' MB';
			});
			ViewTemplate::extend(array('insert_js', 'insert_css'), ViewExtras::class);
			self::extend(function($content) {
				return ViewTemplate::parse($content);
			});
		}
	}
	
	public static function make($file, &$data=array(), $config=array()) {
		$self = new self($config);
		$self->file = $file;
		if ($data instanceof \ArrayObject) $this->_vars = &$data;
		else $self->assign($data);
		
		return $self;
	}
	
	/**
	 * 获取变量循环迭代器
	 * @return \ArrayIterator
	 */
	public function getIterator() {
		return $this->_vars->getIterator();
	}

	/**
	 * @param string|int $name
	 * @return mixed
	 */
	public function __get($name) {
		return (array_key_exists($name, $this->_vars)) ? $this->_vars[$name] : null;
	}

	/**
	 * @param string|int $name
	 * @param mixed $value
	 */
	public function __set($name, $value) {
		$this->_vars[$name] = $value;
	}

	/**
	 * @param string|int $name
	 * @return bool
	 */
	public function __isset($name) {
		return isset($this->_vars[$name]);
	}

	// 接收要注入的变量
	public function assign($key, $value=null) {
		if (is_array($key) or ($key instanceof \Traversable)) {
			foreach ($key as $k => $v) {
				$this->_vars[$k] = $v;
			}
		} else if (!empty($key)) {
			$this->_vars[$key] = $value;
		}
		return $this;
	}
	public function assign_extras($key, $value=null, $once=false) {
		if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->assign_extras($k, $v);
			}
		} else if (!empty($key) and is_string($key)) {
			$this->_vars[$key] = $value;
			//if ($once === true)
				//$this->_once[$key] = $value;
			//else
				self::$_extras[$key] = $value;
		}
		return $this;
	}
	// 接收要注入的新建宏参数
	public function assign_macros($key, $value=null) {
		if (!empty($key) and is_string($key)) {
			if (! array_key_exists($key, self::$_macros) or self::$_macros[$key] != $value) {
				self::$_macros[$key] = $value;
				$this->_vars[$key] = new ViewMacro($value);
			}
		} else if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->assign_macros($k, $v);
			}
		}
		return $this;
	}
	
	public function config($config=null) {
		if (is_array($config)) {
			$this->_config = array_merge($this->_config, $config);
		}
		return $this->_config;
	}
	
	public function data() {
		return $this->_vars;
	}
	
	public function file() {
		return $this->file;
	}
	
	public function path() {
		if (array_key_exists('template_path', $this->_config)) {
			$this->path = $this->_config['template_path'];
			@chdir($this->path);
		} else {
			$this->path = getcwd();
		}
		$this->path = rtrim($this->path, '/') . '/';
		return $this->path;
	}
	
	protected function cache_path() {
		if (array_key_exists('cache_path', $this->_config))
			$this->cache_path = $this->_config['cache_path'];
		$this->cache_path = rtrim($this->cache_path, '/') . '/';
		return $this->cache_path;
	}
	
	protected function expire() {
		if (array_key_exists('cache_expires', $this->_config))
			$this->expire = intval($this->_config['cache_expires']);
		elseif (array_key_exists('expire', $this->_config))
			$this->expire = intval($this->_config['expire']);
		return $this->expire;
	}
	
	public function can_cache() {
		if (array_key_exists('cache', $this->_config))
			$this->cache = $this->_config['cache'];
		return $this->cache;
	}
	
	protected function cacher() {
		if (array_key_exists('cache_driver', $this->_config))
			$this->cacher = $this->_config['cache_driver'];
		return $this->cacher;
	}
	
	public static function exists($file, $path='', $suffix='') {
		if (file_exists($file)) return $file;
		$ext = (! empty($suffix)) ? $suffix : '.view.php';
		$_ext = pathinfo($file, PATHINFO_EXTENSION);
		if (! empty($_ext)) $_ext = '.'.ltrim($_ext, '.');
		$exts = (array) $ext;
		foreach ($exts as $ext) {
			if (! empty($_ext)) {
				if (starts_with($ext, $_ext))
					$ext = str_ireplace($_ext, '', $ext);
			}
			$file = $path . $file . $ext;
			if (file_exists($file)) break;
		}
		if (! file_exists($file))
			return false;
		else
			return $file;
	}
	
	/**
	 * 返回并设置模板文件的真实路径
	 */
	public function real_file($file) {
		if ($file != null)
			$this->file = $file;
		if (empty($this->file)) return false;
		if ($file = self::exists($this->file, $this->path(), $this->_config['suffix'])) {
			$this->file = $file;
			return $this->file;
		} else {
			return false;
		}
	}
	
	public function reset() {
		self::$first_engine = null;
		self::$traces = array();
		self::$_macros = array();
		self::$_extras = array();
	}
	
	public function fetch($file=null, $include=true) {
		if (! $this->real_file($file)) {
			return false;
		}
		
		if ($include) {
			ob_start() and ob_clean();       // Start output buffering
			extract((array) $this->data()); // Extract the vars to local namespace
			include $this->file;           // Include the file
			$contents = ob_get_contents(); // Get the contents of the buffer
			ob_end_clean();               // End buffering and discard
		} else {
			$contents = static::load($this->file);
		}
		
		return $contents;            // Return the contents
	}
	
	public function render($file=null) {
		// 读取模板文件
		if (! $this->real_file($file)) {
			trigger_error(sprintf('未找到模板文件：%s', $file), E_USER_WARNING);
			return false;
		}
		if (! self::$first_engine) {
			self::$first_engine = $this;
			$is_first = true;
		}
		static::$traces[$this->file]['begin'] = microtime(true);
		
		if ($is_first and $this->can_cache()) { // 读取缓存内容，仅针对主模板
			$cached = $this->load_from_cache();
		}
		if ($cached) {
			static::$traces[$this->file]['cached'] = true;
			$contents = $cached;
		} else {
			$contents = $this->fetch($this->file, false);
			static::$traces[$this->file]['origin'] = $contents;
			$this->emit('before', array('file' => $this->file, 'content' => $contents));
			
			// 载入模板解析类
			$parser = new ViewParser($contents, $this);
			$contents = $parser->parse();
			//$contents = ViewParser::load($contents);
			static::$traces[$this->file]['parsed'] = $contents;
			
			if ($is_first and $this->cache) { // 缓存模板引擎解析后的数据
				$this->save_to_cache($contents);
			}
		}
		static::$traces[$this->file]['mid'] = microtime(true);
		if ($is_first) { // 定位主模板
			$contents = $this->padding($contents);
			static::$traces[$this->file]['loaded'] = true;
		} else { // 分配数据
			self::$first_engine->assign($this->data());
		}
		
		static::$traces[$this->file]['end'] = microtime(true);
		static::$traces[$this->file]['cost_time'] = round((static::$traces[$this->file]['end']
			- static::$traces[$this->file]['begin']) * 1000, 2).'ms';
		$this->emit('after', array('file' => $this->file, 'content' => $contents));
		
		return $contents;
	}
	
	public function padding($contents, $isfile=false) {
		ob_start() and ob_clean();
		
		extract((array) $this->data(), EXTR_OVERWRITE);
		extract(array('faddle' => array()), EXTR_OVERWRITE);
		
		try {
			if ($isfile)
				include $contents;
			else
				@eval('?>'.$contents);
		} catch (\Exception $e) {
			ob_end_clean(); throw $e;
		}
		
		return ob_get_clean();
	}
	
	protected function load_from_cache() {
		if (! is_file($this->file)) return false;
		$cache_path = $this->cache_path();
		$expire = $this->expire();
		$cacher = $this->cacher();
		$this->cache_file = $cache_path . md5($this->file) . '';
		// 判断是否存在缓存
		if (file_exists($this->cache_file)) { // 文件有更新则不加载
			if (filemtime($this->cache_file) < filemtime($this->file)) return false;
		}
		// 建立缓存驱动
		//if (! $this->cacher) $this->cacher = new SaeKVHelper();
		$data = CacheHelper::load($this->cache_file, $cacher, filemtime($this->file));
		if ($data and is_array($data)) {
			if (isset($data['extras'])) $this->assign_extras($data['extras']);
			$this->assign_macros($data['macros']); // 宏数据需要单独载入
			return $data['contents'];
		} else return false;
		
		//$this->assign_macros(unserialize(static::load($this->cache_file . '.macros')));
		//return static::load($this->cache_file);
	}
	
	protected function save_to_cache($contents) {
		$data = array(
				'file' => $this->file,
				'macros' => self::$_macros, // 保存宏数据，否则无法解析宏函数
				'extras' => self::$_extras, // 保存视图扩展数据
				'contents' => $contents
			);
		CacheHelper::save($this->cache_file, $data, $this->cacher, $this->expire);
		
		//static::save($this->cache_file, $contents);
		//static::save($this->cache_file . '.macros', serialize(self::$_macros));
	}
	
	/**
	 * 清理缓存的视图模板文件
	 * @param null $path
	 */
	public function clean($path=null) {
		if ($path == null) {
			$path = $this->cache_path();
		} else {
			$path = $this->cache_path().md5($path) . '';
		}
		$path = glob($path . '*');
		foreach ((array) $path as $file) {
			if (is_file($file)) @unlink($file);
		}
	}
	
	public static function after($callback) {
		if (isset($callback))
			static::$after_callbacks[] = $callback;
	}
	
	public static function before($callback) {
		if (isset($callback))
			static::$before_callbacks[] = $callback;
	}
	
	protected function emit($event_name, $args=null) {
		switch (strtolower($event_name)) {
		case 'before': $queue = self::$before_callbacks;
		break;
		case 'after': $queue = self::$after_callbacks;
		break;
		default: return;
		}
		try {
			foreach ($queue as $callback) {
				if (is_callable($callback)) {
					call_user_func_array($callback, $args);
				} else {
					
				}
			}
		} catch (Exception $err) {
			
		}
	}
	
	/**
	 * 扩展视图变量修饰器
	 * @param $name string 名称
	 * @param func callable 变量修饰器函数 
	 */
	public static function extend_modifier($name, $func) {
		if (empty($name))
			return false;
		if (!is_callable($func))
			return false;
		self::$modifiers[$name] = $func;
		return true;
	}
	
	public static function call_modifier() {
		$args = func_get_args();
		$name = trim($args[0]);
		if (empty($name)) {
			return false;
		}
		$func =  self::$modifiers[strtolower($name)];
		if (empty($func) ) {
			if (function_exists($name)) {
				$func = $name;
			} else if (method_exists(TextUtils::class, $name)) {
				$func = array(TextUtils::class, $name);
			} else  {
				return false;
			}
		}
		try {
			$ret = call_user_func_array($func, array_slice($args,1));
		} catch(\Exception $e) {
			trigger_error(sprintf('过滤器[%s]解析出错：%s', $name, $e->getMessage()), E_USER_WARNING);
			return false;
		}
		return $ret;
	}
	
	/**
	 * 扩展视图指令
	 * @param $func 指令函数
	 */
	public static function extend(\Closure $func) {
		if (!is_callable($func))
			return false;
		self::$extends[] = $func;
		return true;
	}
	
	public static function modifier_exists($name) {
		$name = trim($name);
		return (array_key_exists(strtolower($name), self::$modifiers) 
				or function_exists($name) 
				or method_exists(TextUtils::class, $name));
	}
	
	private function init_modifier() {
		self::extend_modifier('upper', function($input) {
			if (!is_string($input)) return $input;
			return mb_strtoupper($input, 'utf-8');
		});
		self::extend_modifier('lower', function($input) {
			if (!is_string($input)) return $input;
			return mb_strtolower($input, 'utf-8');
		});
		self::extend_modifier('title', function($input) { //capitalize
			if (!is_string($input)) return $input;
			if (function_exists('mb_convert_case')) {
				return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
			}
			return ucwords($input);
		});
		self::extend_modifier('truncate', function($input, $len) {
			if (empty($len)) return $input;
			$len = intval($len);
			return TextUtils::usubstr($input, $len);
		});
		self::extend_modifier('len', function($input) {
			if (is_array($input))
				return count($input);
			elseif (is_string($input))
				return mb_strlen($input, 'utf-8');
			else
				return 0;
		});
		self::extend_modifier('trims', function($input) {
			if (is_string($input)) return trim($input);
			if (is_array($input)) {
				foreach ($input as $k => $v) {
					if (empty($v)) unset($input[$k]);
				}
			}
			return $input;
		});
		self::extend_modifier('lcat', function($input, $cat) {
			if (empty($cat)) return $input;
			if (empty($input)) return '';
			return $cat.$input;
		});
		self::extend_modifier('rcat', function($input, $cat) {
			if (empty($cat)) return $input;
			if (empty($input)) return '';
			return $input.$cat;
		});
		self::extend_modifier('wrap', function($input, $before='', $after='') {
			if (empty($input)) return '';
			return $before.$input.$after;
		});
		self::extend_modifier('indent', function($input, $chars=4, $char=' ') {
			return preg_replace('!^!m',str_repeat($char, $chars), $input);
		});
		self::extend_modifier('lines', function($input) {
			return count(preg_split('/[\r\n]+/', (string) $input));
		});
		self::extend_modifier('default', function($input, $default) {
			if (empty($default)) return $input;
			if (empty($input)) return $default;
			return $input;
		});
		self::extend_modifier('replace', function($input, $from, $to='') {
			if (empty($from)) return $input;
			if (empty($input)) return '';
			if (strpos($from, '/') === 0 and substr_count($from, '/') > 1)
				return preg_replace($from, $to, $input);
			return str_replace($from, $to, $input);
		});
		self::extend_modifier('safe', function($input) {
			return htmlspecialchars($input, ENT_QUOTES, 'UTF-8', false); //htmlentities
		});
		self::extend_modifier('if', function($input, $correct, $incorrect='') {
			if ($input) return $correct;
			else return $incorrect;
		});
		self::extend_modifier('get', function($input, $prop='', $def=null, $kv=0) {
			if (is_object($input)) {
				return (property_exists($input, $prop)) ? $input->$prop : $def;
			}
			if (!is_array($input)) return $input;
			if (is_int($prop)) {
				if ($prop < 0) {
					$input = array_reverse($input);
					while ($prop < -1) {if (!each($input)) return $def; $prop++;}
				} else {
					while ($prop > 0) {if (!each($input)) return $def; $prop--;}
				}
				if ($kv) return array(key($input) => current($input));
				else return current($input);
			}
			if (! isset($input[$prop])) return $def;
			if ($kv) return array($prop, $input[$prop]);
			else return $input[$prop];
		});
		self::extend_modifier('set', function($input, $prop, $value) {
			if (is_object($input)) $input->$prop = $value;
			elseif (is_array($input)) $input[$prop] = $value;
			return $input;
		});
		self::extend_modifier('iter', function($input, $step=1, $inival=0) {
			static $_iter = array();
			if (! isset($_iter[$input])) return $_iter[$input] = intval($inival);
			return $_iter[$input] = $_iter[$input] + intval($step);
		});
		self::extend_modifier('json', function($input, $pretty=false) {
			if ($pretty) return json_encode($input, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
			else return json_encode($input, JSON_UNESCAPED_UNICODE);
		});
		self::extend_modifier('limit', function($input, $st=0, $len=0) {
			if ($len === 0) {$len = $st; $st = 0;}
			if ($len === 0) return $input;
			$st = intval($st);
			if (is_array($input)) {
				return array_slice($input, $st, $len);
			} else {
				return mb_substr($input, $st, $len);
			}
		});
		self::extend_modifier('filter', function($input, $dest=null) {
			if (is_array($input)) {
				return filter_var_array($input, $dest);
			} else {
				if (is_int($dest)) return filter_var($input, $dest);
				else return filter_var($input, FILTER_CALLBACK, array('options' => $dest));
			}
		});
		self::extend_modifier('order', function($input, $by, $asc=true) {
			if (empty($by) or !is_array($input)) return $input;
			$keysvalue = $new_array = array();
			foreach ($input as $k => $v) {
				if (is_array($v) and isset($v[$by])) { $keysvalue[$k] = $v[$by];
				} else { $keysvalue = array(); break; }
			}
			if ($asc) asort($keysvalue);
			else arsort($keysvalue);
			reset($keysvalue);
			foreach ($keysvalue as $k => $v) { $new_array[$k] = $input[$k]; }
			return $new_array;
		});
		self::extend_modifier('foreach', function($input, $tpl, $key=false, $params=null) {
			$input = (array) $input;
			$output = ''; $i = 0;
			foreach ($input as $k => $v) {
				if ($key) $output .= call_user_func($tpl, $k, $v, $i, $params);
				else  $output .= call_user_func($tpl, $v, $i, $params);
				$i++;
			}
			return $output;
		});
		self::extend_modifier('call', function($input, $sub) {
			if (is_callable($input)) return call_user_func($input, $sub);
			elseif (is_array($input)) return $input[$sub];
			elseif (is_object($input) and method_exists($input, $sub))
				return call_user_func(array($input, $sub), array_slice(func_get_args(), 2));
			return ($input);
		});
		
		$arr = array('eq','seq','neq','lt','gt','lte','gte','true','false','like','contains');
		foreach ($arr as $a) {
			self::extend_modifier($a, function() use ($a) {
				$args = func_get_args();
				array_unshift($args, $a);
				return call_user_func_array(array(self, 'modify_if'), $args);
			});
		}
		
	}
	
	private static function modify_if($operator, $input, $condition, $true_val, $false_val=false) {
		if (! isset($true_val)) {
			$true_val = $input;
		}
		switch ($operator) {
			case '':
			case '=':
			case 'eq':
			default:
				$operator='==';
				break;
			case '==':
			case 'seq':
				$operator='===';
				break;
			case '<':
			case 'lt':
				$operator='<';
				break;
			case '>':
			case 'gt':
				$operator='>';
				break;
			case '<=':
			case 'lte':
				$operator='<=';
				break;
			case '>=':
			case 'gte':
				$operator='>=';
				break;
			case '!=':
			case 'neq':
				$operator = '!=';
				break;
			case 'true':
				$operator = '==';
				$true_val = $condition;
				$false_val = false;
				$condition = true;
				break;
			case 'false':
				$operator = '==';
				$true_val = $condition;
				$false_val = true;
				$condition = false;
				break;
			case 'contains':
				if (is_array($input) and ! is_array($condition)) {
					$needle = (string) $condition;
					$haystack = $input;
				} elseif (is_array($condition) and ! is_array($input)) {
					$needle = (string) $input;
					$haystack = $condition;
				} elseif (is_array($condition) and is_array($input)) {
					$intersect = array_intersect($input, $condition);
					if ($intersect == $input or $intersect == $condition)
						return $true_val;
					else
						return $false_val;
				} else {
					$input = (string) $input;
					$condition = (string) $condition;
					if (strstr($condition, $input) ?: strstr($input, $condition))
						return $true_val;
					else
						return $false_val;
				}
				if (array_search($needle, $haystack) !== false)
					return $true_val;
				else
					return $false_val;
				break;
			case 'like':
				if (is_array($input)) $input = implode(' ', $input);
				if (is_array($condition)) $condition = implode(' ', $condition);
				if (stristr($condition, $input) ?: stristr($input, $condition))
					return $true_val;
				else
					return $false_val;
				break;
		}
		$ret = $input;
		if (eval('return ("'.$input.'" '.$operator.' "'.$condition.'");')) {
			$ret = $true_val;
		} else {
			$ret = $false_val;
		}
		return $ret;
	}

	/*
	 * 读取文件内容
	 * @param string $path 文件路径
	 */
	public static function load($path, $data='') {
		if (strlen($path) < PHP_MAXPATHLEN && is_file($path) && is_readable($path)) {
			$data = @file_get_contents($path);
			// strip BOM, if any
			if (substr($data, 0, 3) == "\xef\xbb\xbf") {
				$data = substr($data, 3);
			}
		}
		return $data;
	}
	
	/*
	 * 保存文件内容
	 * @param srting $path 文件路径
	 */
	public static function save($path, $data='') {
		$fp = fopen($path, 'w+');
		if ($fp and flock($fp, LOCK_EX)) {
			$result = fwrite($fp, $data);
			flock($fp, LOCK_UN);
		} else {
			return file_put_contents($path, $data);
		}
		if ($fp) fclose($fp);
		return $result;
	}

}
