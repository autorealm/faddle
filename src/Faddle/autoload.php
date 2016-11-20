<?php

if (! defined('FADDLE_PATH')) {
	define('FADDLE_PATH', dirname(dirname(__FILE__)));
}
if (! defined('FADDLE_VERSION')) {
	define('FADDLE_VERSION', 'Faddle-1.0x');
}
if (! defined('FADDLE_AT')) {
	define('FADDLE_AT', microtime(true));
}
if ( ! defined('CRLF')) {
	define('CRLF', "\r\n");
}
if ( ! defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}
if ( ! defined('SELF')) {
	define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
}
if ( ! defined('DATETIME_FORMAT')) {
	define('DATETIME_FORMAT', 'Y-m-d H:i:s');
}

set_include_path(get_include_path() . PATH_SEPARATOR . FADDLE_PATH);

if (!function_exists('combine_arr')) {
	function combine_arr($a, $b) {
		$acount = count($a);
		$bcount = count($b);
		$size = ($acount > $bcount) ? $bcount : $acount;
		$a = array_slice($a, 0, $size);
		$b = array_slice($b, 0, $size);
		return array_combine($a, $b);
	}
	
}

if (!function_exists('starts_with')) {
	function starts_with($haystack, $needles) {
		foreach ((array) $needles as $needle) {
			if (strpos($haystack, $needle) === 0) return true;
		}
		return false;
	}
}

if (!function_exists('ends_with')) {
	function ends_with($haystack, $needles) {
		foreach ((array) $needles as $needle) {
			if (strrchr($haystack, $needle) == $needle) return true;
		}
		return false;
	}
}

if (! function_exists('load')) {
	function load($file) {
		if (is_file($file)) return require($file);
		if (is_callable($file)) {
			$args = array_slice(func_get_args(), 1);
			return call_user_func_array($file, $args);
		}
		return $file;
	}
}

if (! function_exists('value')) {
	function value($value) {
		return $value instanceof Closure ? $value() : $value;
	}
}

if (! function_exists('with')) {
	function with($object) {
		return $object;
	}
}

if (!function_exists('mstimer')) {
	$__t0__ = microtime();
	
	/**
	 * 毫秒计时函数
	 * @param number $mode
	 * @return void|string
	 */
	function mstimer($mode=1) {
		static $t;
		if (! $mode) {$t = microtime(); return 0;}
		elseif ($mode == 1) $t0 = $__t0__;
		else $t0 = $t ?: $__t0__;
		$t1 = microtime();
		list($m0, $s0) = explode(' ', $t0);
		list($m1, $s1) = explode(' ', $t1);
		return sprintf("%.3f ms",($s1+$m1-$s0-$m0)*1000);
	}
}

if (!function_exists('call_modifier')) {
	function call_modifier() {
		return call_user_func_array(array(\Faddle\View\ViewEngine::class, 'call_modifier'), func_get_args());
	}
}
if (!function_exists('extend_modifier')) {
	function extend_modifier($name, $func) {
		return call_user_func(array(\Faddle\View\ViewEngine::class, 'extend_modifier'), $name, $func);
	}
}
if (!function_exists('extend_template')) {
	function extend_template(\Closure $func) {
		return call_user_func(array(\Faddle\View\ViewEngine::class, 'extend'), $func);
	}
}
if (!function_exists('extend_view')) {
	function extend_view($name, \Closure $func) {
		return call_user_func(array(\Faddle\View::class, 'extend'), $name, $func);
	}
}
if (!function_exists('faddle_route')) {
	function faddle_route() {
		return call_user_func_array(array(\Faddle\Router\Route::class, 'generate'), func_get_args());
	}
}
if (!function_exists('write_log')) {
	function write_log() {
		return call_user_func_array(array(\Faddle\Support\Logger::class, 'write'), func_get_args());
	}
}
if (!function_exists('show_message')) {
	function show_message() {
		return call_user_func_array(array(\Faddle\Http\Response::class, 'showMessage'), func_get_args());
	}
}
if (!function_exists('show_debug')) {
	function show_debug() {
		return call_user_func_array(array(\Faddle\Http\Response::class, 'debug'), func_get_args());
	}
}

function autoload($class) {
	$class_file = FADDLE_PATH. DS. str_replace('\\', DS, ($class)).'.php';
	if (file_exists($class_file)) {
		require_once($class_file);
		return true;
	} else {
		return false;
	}
}
spl_autoload_register('autoload');

