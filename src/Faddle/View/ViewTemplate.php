<?php namespace Faddle\View;

/**
 * 视图扩展类
 * @author KYO
 * @since 2015-10-14
 *
 */
class ViewTemplate {

	/**
	 * 存放已注册的模板扩展解析 Helper
	 *
	 * @var array
	 */
	static public $helpers = array();

	/**
	 * 注册模板扩展解析 Helper
	 *
	 * @param  string   $name
	 * @param  Closure  $helper
	 * @return void
	 */  
	public static function helper($name, \Closure $helper) {
		static::$helpers[$name] = $helper;
	}

	/**
	 * 注册模板扩展解析 Helper
	 *
	 * @param  array|string   $methods
	 * @param  object  $object
	 * @return void
	 */  
	public static function extend($methods, $object) {
		$methods = (array) $methods;
		foreach ($methods as $method) {
			if (method_exists($object, $method))
				static::$helpers[$method] = array($object, $method);
		}
	}
	
	/**
	 * 解析模板内容
	 *
	 * @return string
	 */
	public static function parse($content) {
		if(count(static::$helpers) == 0) {
			return $content;
		}
		
		$names = array();
		
		foreach(static::$helpers as $name => $helper) {
			$names[] = preg_quote($name, '/');
		}
		
		$regexp = '/[\s]{0,}('.implode('|', $names).')(.*)[\s]{0,}/u';
		
		return preg_replace_callback($regexp, function($match) {
			list($_, $name, $params) = $match;
			
			if( ! empty($params)) {
				$params = addcslashes($params, '\'');
				$params = preg_replace('/ (.*?)="(.*?)"/', '\'$1\'=>"$2",', $params);
				$params = preg_replace('/ (.*?)=\'(.*?)\'/', '\'$1\'=>\'$2\',', $params);
				$params = substr($params, 0, -1);
			}
			
			return '<?php echo \\Faddle\\View\\ViewTemplate::call(\''.$name.'\', array('.$params.')); ?>';
		}, $content);
	}

	/**
	 * 执行模板扩展解析.
	 *
	 * @param  string  $name
	 * @param  array   $params
	 * @return mixed
	 */
	public static function call($name, $params = array()) {
		$helper = static::$helpers[$name];
		if (is_array($helper)) return call_user_func($helper, $params);
		return $helper($params);
	}

}
