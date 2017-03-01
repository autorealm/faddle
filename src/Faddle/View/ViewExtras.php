<?php namespace Faddle\View;

/**
 * 视图附加类
 * @author KYO
 * @since 2015-10-14
 */
class ViewExtras {

	public function __call($method, $args) {
		
	}
	
	public static function insert_js($args) {
		if (! is_array($args) or empty($args)) return '';
		$files = (array_key_exists('file', $args)) ? explode(',', $args['file']) : [];
		$path = (array_key_exists('path', $args)) ? strval($args['path']) : '';
		$ver = (array_key_exists('ver', $args)) ? '?v='. strval($args['ver']) : '';
		$output = '';
		foreach ($files as $file) {
			$file = trim($file); $path = trim($path);
			if (!empty($path)) $file = rtrim($path, '/') . '/' . ltrim($file, '/');
			$output .= "<script type='text/javascript' src='$file$ver'></script>\r\n";
		}
		return $output;
	}
	
	public static function insert_css($args) {
		if (! is_array($args) or empty($args)) return '';
		$files = (array_key_exists('file', $args)) ? explode(',', $args['file']) : [];
		$path = (array_key_exists('path', $args)) ? strval($args['path']) : '';
		$ver = (array_key_exists('ver', $args)) ? '?v='. strval($args['ver']) : '';
		$output = '';
		foreach ($files as $file) {
			$file = trim($file); $path = trim($path);
			if (!empty($path)) $file = rtrim($path, '/') . '/' . ltrim($file, '/');
			$output .= "<link rel='stylesheet' type='text/css' media='all' href='$file$ver' />\r\n";
		}
		return $output;
	}
	
	public static function repeat($args) {
		if (! is_array($args) or empty($args)) return '';
		
	}

/**
+ add view assets.

mothod: assets($name, [$params]);

set: View::setAssets($config);

config: array(
base_path => '',
base_url => '',
depends => [],
...
publish => [
    'name' => [
        'path' => '',
        'only' => []
    ]
],
map => [
    $name => $path,
    'jquery' => 'static/js/jquery.min.js'
],
bundles => [
    js => [
        
    ],
    css => [
        
    ]
],
all => [
    source_path => '',
    target_path => '',
    commands => [
        'less' => '',
        ...
    ],
    files => [],
    js => '',
    css => '',
]
)
*/

}
