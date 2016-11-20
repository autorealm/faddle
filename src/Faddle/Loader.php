<?php namespace Faddle;

/**
 * 加载类
 */
final class Loader {
	protected $classmap = [];
	protected $finders = [];
	protected $paths = [];
	private static $instance;
	
	public function __construct() {
		self::$instance = & $this;
		/*try {
			$this->init();
		} catch (InvalidArgumentException $iae) {
			exit($iae->getMessage());
		}*/
	}
	
	private function __clone() {}
	
	/**
	 * 获得一个加载器单实例对象
	 */
	public static function instance() {
		if (! self::$instance) self::$instance = new self();
		return self::$instance;
	}
	
	/** 静态方法调用 */
	public static function __callstatic($method, $params) {
		
	}
	
	/**
	 * 注册加载器至命名空间（SPL）自动加载栈
	 * @param boolean Whether to prepend the autoloader or not
	 * @access public
	 */
	public function register($loader=null, $prepend=false) {
		if ($loader instanceof \Closure) {
			return spl_autoload_register($loader, true, (boolean) $prepend);
		}
		return spl_autoload_register([$this, 'load'], true, $prepend);
	}
	
	/**
	 * 卸载加载器从命名空间（SPL）自动加载栈
	 * @access public
	 */
	public function unregister($loader=null) {
		if ($loader instanceof \Closure) {
			return spl_autoload_unregister($loader);
		}
		return spl_autoload_unregister([$this, 'load']);
	}

	/**
	 * 自定义查找器
	 * @access public
	 */
	public function finder($finder) {
		if (! isset($finder)) return $this->finders;
		if ($finder instanceof \Closure) {
			$this->finders[] = $finder;
		} else if (is_array($finder)) {
			$this->finders = $finder;
		}
		return $this;
	}

	/**
	 * 添加一个类路径
	 * @param  string $name 类的名称
	 * @param  string $name 类文件位置路径
	 * @return Faddle\Loader
	 */
	public function add($name, $path) {
		$this->classmap[$name] = $path;
		return $this;
	}
	
	/**
	 * 包含一个搜索路径
	 * @param string|array $paths 目录路径
	 */
	public function includes($paths) {
		if (isset($paths)) {
			foreach ((array) $paths as $path) {
				if (is_dir($path) and !in_array($path, $this->paths)) {
					$this->paths[] = $path;
				}
			}
		}
		return $this;
	}
	
	/**
	 * 导入类图至加载器
	 * @param  array $maps 类图
	 * @return Faddle\Loader
	 */
	public function import(array $maps) {
		foreach ($maps as $name => $path) {
			if (is_int($name) and is_file($path)) {
				include_once($path);
				continue;
			}
			$this->add($name, $path);
		}
		return $this;
	}
	
	/**
	 * 检查一个类是否在类图中
	 * @param  string $name 类的名称
	 * @return boolean
	 */
	public function has($name) {
		return (array_key_exists($name, $this->classmap));
	}
	
	/**
	 * 检查一个  class, interface 或者 trait 是否已经加载
	 * @param  string $class
	 * @return boolean
	 */
	protected function is_loaded($class) {
		return (
			class_exists($class, false) ||
			interface_exists($class, false) ||
			trait_exists($class, false)
		);
	}
	
	/**
	 * 加载类或接口调用的方法
	 * @param string class name
	 * @return boolean true if class loaded or false in otherwise
	 */
	public function load($class) {
		if ($this->is_loaded($class)) {
			return true;
		}
		if (! empty($this->finders) && is_array($this->finders)) {
			foreach ($this->finders as $finder) {
				if ($file = call_user_func($finder, $class)) {
					if (file_exists($file)) {
						require($file);
						return true;
					}
				}
			}
			
		}
		
		$paths = $this->paths; //explode(PATH_SEPARATOR, \get_include_path());
		foreach ($paths as $path) {
			$file = $this->find_by_psr($class, $path);
			if (file_exists($file)) {
				require($file);
				return true;
			}
		}
		
		if ($file = $this->search($class)) {
			if (is_file($file)) {
				include($file);
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Search for classes
	 * @param string class name
	 * @access private
	 * @return string class path or false in otherwise
	 */
	private function search($class) {
		// Firstly: if class exists in map return to class path
		if (array_key_exists($class, $this->classmap)) {
			return $this->classmap[$class];
		}
		
		// Secondly: if class not exists in map
		// Checking if class loaded as PSR-0 standard or Set class name with .php suffix
		$position = strrpos($class, '\\');
		$classpath = (false !== $position) ? $this->find($class) : $class.'.php';
		
		return $classpath;
	}
	
	public function find_by_psr($class, $path) {
		return rtrim($path, '/') . DIRECTORY_SEPARATOR . str_replace(['_', '/', '\\'], DIRECTORY_SEPARATOR, $class) . '.php';
		
	}
	
	public function find($classname) {
		$parts = explode('\\', ltrim($classname, '\\'));
		if (false !== strpos(end($parts), '_')) {
			array_splice($parts, -1, 1, explode('_', current($parts)));
		}
		$filename = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
		
		if ($filename = stream_resolve_include_path($filename)) {
			return $filename;
		} else {
			return false;
		}
	}
	
	public function __invoke($classname) {
		require $this->find($classname);
	}
	
	/**
	 * 创建一个命名空间别名
	 * @param string  The original class
	 * @param string  The new namespace for this class
	 * @param boolean Put original class name with alias by default false
	 * @access public
	 */
	public function namespace_alias($original, $alias=null, $with_original=false) {
		$alias = ((isset($alias)) ? rtrim($alias, '\\') : $alias);
		if ($with_original) {
			// Get clean class name without any namespaces
			$exp = explode('\\', $original);
			$alias = array_pop($exp).'\\'.$alias;
		}
		class_alias($original, $alias);
	}
	

}