<?php namespace Faddle\View;

/**
 * 视图宏类
 * @author KYO
 * @since 2015-10-14
 *
 */
class ViewMacro extends \stdClass {

	public function __construct($macros) {
		
		foreach ((array) $macros as $key => $val) {
			if (! is_string($key) or ! is_array($val) or count($val) < 2) continue;
			$this->$key = function() use ($val) {
				$args = combine_arr(array_keys($val[0]), func_get_args()[0]);
				$args = array_merge($val[0], $args);
				if (ViewParser::$commands['macro_process_mode'])
					$body = ViewParser::load($val[1]); //全处理模式
				else
					$body = ViewParser::process($val[1]); //只做语句块轻量处理
				ob_start() and ob_clean();
				extract($args, EXTR_OVERWRITE);
				
				try {
					eval('?>'.$body);
				} catch (\Exception $e) {
					ob_end_clean(); throw $e;
				}
				
				$body = ob_get_clean();
				
				return $body;
			};
		}
		
	}
	
	public function __call($method, $args) {
		if (isset($this->$method)) {
			$func = $this->$method;
			return $func($args);
		} else {
			return false;
		}
	}
	
	
}
