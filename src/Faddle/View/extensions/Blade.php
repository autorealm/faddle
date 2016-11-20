<?php

class Blade {

	public static function init($config, $storage=null) {
		if (! defined('CRLF')) {
			define('CRLF', "\r\n");
		}
		if (! defined('DS')) {
			define('DS', DIRECTORY_SEPARATOR);
		}
		if (! defined('BLADE_EXT')) {
			$blade_ext = isset($config['suffix']) ? $config['suffix'] : '.blade.php';
			define('BLADE_EXT', $blade_ext);
		}
		if (! defined('TEMPLATE_PATH')) {
			$template_path = isset($config['template_path']) ? $config['template_path'] : '/templates';
			define('TEMPLATE_PATH', $template_path);
		}
		
		$storage_path = isset($config['storage_path']) ? $config['storage_path'] : 'views';
		set_storage_path($storage_path);
		set_storage($storage);
		
		$libraries = array( 'laravel/blade', 'laravel/section', 'laravel/view', 'laravel/event' );
		foreach ( $libraries as $filename ) {
			$file = rtrim(__DIR__, '/') . '/' . $filename . '.php';
			require_once($file);
		}
		
		Laravel\Blade::sharpen();
		
		return 'blade';
	}

	protected static $compilers = array(
		'wpquery',
		'wpposts',
		'wpempty',
		'wpend',
		'debug'
	);

	/**
	 *
	 */
	public static function compile_string( $value, $view = null ) {

		/*foreach (static::$compilers as $compiler)
		{
			$method = "compile_{$compiler}";

			$value = static::$method($value, $view);
		}*/

		return $value;
	}

	/**
	 *
	 */
	protected static function compile_wpposts( $value ) {

		return str_replace('@wpposts', '<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>', $value);
	}

	/**
	 *
	 */
	protected static function compile_wpquery( $value ) {

		$pattern = '/(\s*)@wpquery(\s*\(.*\))/';
		$replacement  = '$1<?php $bladequery = new WP_Query$2; ';
		$replacement .= 'if ( $bladequery->have_posts() ) : ';
		$replacement .= 'while ( $bladequery->have_posts() ) : ';
		$replacement .= '$bladequery->the_post(); ?> ';

		return preg_replace( $pattern, $replacement, $value );
	}

	/**
	 *
	 */
	protected static function compile_wpempty( $value ) {

		return str_replace('@wpempty', '<?php endwhile; ?><?php else: ?>', $value);
	}

	/**
	 *
	 */
	protected static function compile_wpend( $value ) {

		return str_replace('@wpend', '<?php endif; wp_reset_postdata(); ?>', $value);
	}

	/**
	 *
	 */
	protected static function compile_debug( $value ) {

		// Done last
		if( strpos( $value, '@debug' ) )
			die( $value );
		return $value;
	}

}

if (!function_exists('view')) {
	function view($path, $data = array()) {
		return Laravel\View::make($path, $data);
	}
}

if (!function_exists('starts_with')) {
	function starts_with($haystack, $needles)
	{
		foreach ((array) $needles as $needle)
		{
			if (strpos($haystack, $needle) === 0) return true;
		}
	
		return false;
	}
}

if (!function_exists('str_contains')) {
	/**
	 * Determine if a given string contains a given sub-string.
	 *
	 * @param  string        $haystack
	 * @param  string|array  $needle
	 * @return bool
	 */
	function str_contains($haystack, $needle)
	{
		foreach ((array) $needle as $n)
		{
			if (strpos($haystack, $n) !== false) return true;
		}
	
		return false;
	}
}

if (!function_exists('set_storage_path')) {
	function set_storage_path($path) {
		$GLOBALS[ 'blade_storage_path' ] = $path;
	}
}

if (!function_exists('set_storage')) {
	function set_storage($storage) {
		$GLOBALS[ 'blade_storage' ] = $storage;
	}
}

/*if (!function_exists('get_template_directory')) {
	function get_template_directory() {
		return array (
			APP_PATH . '/',
			APP_PATH . '/',
		);
	}
}*/
