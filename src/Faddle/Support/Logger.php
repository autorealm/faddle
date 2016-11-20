<?php namespace Faddle\Support;

/**
 * Logging Class
 * 
 */
class Logger {
	
	const DEBUG   = 0;
	const INFO    = 1;
	const WARNING = 2;
	const ERROR   = 3;
	const FATAL   = 4;
	const UNKNOWN = 5;
	
	public static $logs = array();

	protected static function log($type='info', $cat='', $msg='') {
		if (func_num_args() == 2) {
			$msg = $cat;
			$cat = '';
		}
		if ($msg instanceof Exception) {
			$msg = $msg->__toString();
		}
		self::$logs[] = array(
			'time' => self::get_millisecond_date(),
			'type' => strtoupper($type),
			'cat'  => $cat,
			'msg'  => $msg
		);
		self::trace();
		return self;
	}
	
	public static function d($cat='', $msg='') {
		return self::log('debug', $cat, $msg);
	}
	
	public static function i($cat='', $msg='') {
		return self::log('info', $cat, $msg);
	}
	
	public static function e($cat='', $msg='') {
		return self::log('error', $cat, $msg);
	}
	
	public static function w($cat='', $msg='') {
		return self::log('warn', $cat, $msg);
	}
	
	public static function f($cat='', $msg='') {
		return self::log('fatal', $cat, $msg);
	}
	
	public static function trace($debugtrace=null) {
		if (empty($debugtrace)) {
			$debugtrace = debug_backtrace();
		}
		for ($i = 0; $i < count($debugtrace); $i++) {
			$trace = $debugtrace[$i];
			if (empty($trace['file'])) {
				continue;
			}
			self::$logs[count(self::$logs)-1]['traces'][] = $trace;
		}
		return self;
	}
	
	public static function error_log() {
		foreach (self::$logs as $log) {
			error_log('Loger' .  implode(' ', $log));
		}
		
	}
	
	public static function to_json() {
		return json_encode(self::$logs, JSON_UNESCAPED_UNICODE);
	}
	
	public static function to_xml() {
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n";
		$content .= "<logs>";
		foreach ((self::$logs) as $k => $v) {
			$content .= "<log><type>" . ($v['type']) . "</type>\n";
			$content .= "<message><![CDATA[" . ($v['msg']) . "]]></message></log>\n";
		}
		$content .= "</logs>";
		return $content;
	}
	
	
	/**
	 * Render a text string as markdown
	 * 
	 * @param string $str   The text string to parse
	 * @param array $args   Optional arguments to be parsed by markdown
	 * @return string
	 */
	public static function markdown($str, $args = null) {
		// Create our markdown parse/conversion regex's
		$md = array(
			'/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
			'/\*\*([^\*]++)\*\*/'          => '<strong>$1</strong>',
			'/\*([^\*]++)\*/'              => '<em>$1</em>'
		);
		
		// Let's make our arguments more "magical"
		$args = func_get_args(); // Grab all of our passed args
		$str = array_shift($args); // Remove the initial arg from the array (and set the $str to it)
		if (isset($args[0]) && is_array($args[0])) {
			$args = $args[0];
		}
		
		// Encode our args so we can insert them into an HTML string
		foreach ($args as &$arg) {
			$arg = htmlentities($arg, ENT_QUOTES, 'UTF-8');
		}
		
		// Actually do our markdown conversion
		return vsprintf(preg_replace(array_keys($md), $md, $str), $args);
	}
	
	public static function get_millisecond_date($float=false) {
		list($usec, $sec) = explode(" ", microtime());
		if ($float) {
			$msec = ((float)$usec + (float)$sec);
		} else {
			$msec = round($usec*1000);
		}
		return date("Y-m-d H:i:s." . $msec . " (T)");
	}
	
	public static function get_millisecond_time($date=false) {
		list($usec, $sec) = explode(' ', microtime());
		//$msec = ((float)$usec + (float)$sec);
		$msec = sprintf('%03d', round($usec*1000));
		return date(($date ? 'Y-m-d ' : '') . 'H:i:s.' . $msec . '');
	}
	
	// --------------------------------------------------------------------
	
	public static $name_prefix = 'logs-';
	public static $log_path = '/logs/';
	
	public static function write($message, $level='DEBUG', $log_path=null, $name_prefix=null) {
		if (is_string($log_path)) static::$log_path = rtrim($log_path, '/') . '/';
		if (is_string($name_prefix)) static::$name_prefix = $name_prefix;
		$append_time = (strrchr($name_prefix, '-') == '-' or strrchr($name_prefix, '_') == '_' or strrchr($name_prefix, '.') == '.');
		$level = strtoupper($level);
		$date = new \DateTime();
		$log = static::$log_path . static::$name_prefix . ($append_time ? $date->format('Ymd') : '') .'.log';
		if (is_dir(static::$log_path)) {
			if (!file_exists($log)) {
				if (! $fh  = fopen($log, 'a+')) return false;
				$logcontent = '[' . static::get_millisecond_time(!$append_time) ."]\t[$level]\r\n" . $message ."\r\n";
				fwrite($fh, $logcontent);
				fclose($fh);
			} else {
				$logcontent = '[' . static::get_millisecond_time(!$append_time) ."]\t[$level]\r\n" . $message ."\r\n\r\n";
				$logcontent = $logcontent . file_get_contents($log);
				file_put_contents($log, $logcontent);
			}
		} else {
			if (@mkdir(static::$log_path, 0777) === true)  {
				static::write($message);  
			} else return false;
		}
		return true;
	}
	
	// --------------------------------------------------------------------
	
	protected $_log_path;
	protected $_threshold = 1;
	protected $_date_fmt = 'Y-m-d H:i:s';
	protected $_sae_enabled = TRUE;
	protected $_levels = array(
			'ERROR' => '1',
			'DEBUG' => '2',
			'INFO' => '3',
			'ALL' => '4' 
	);
	
	/**
	 * Constructor
	 */
	public function __construct(array $config) {
		if (is_numeric($config['log_threshold'])) {
			$this->_threshold = $config['log_threshold'];
		}
		
		if (class_exists('\SaeKV')) {
			$this->_sae_enabled = TRUE;
		} else {
			$this->_log_path = ($config['log_path'] != '') ? $config['log_path'] : '/logs/';
			
			if (!is_dir($this->_log_path) or !is_really_writable($this->_log_path)) {
				$this->_sae_enabled = FALSE;
			}
			if ($config['log_date_format'] != '') {
				$this->_date_fmt = $config['log_date_format'];
			}
		}
	}
	
	/**
	 * 针对SAE的日志输出，在日志中心中查看，选择debug选项
	 * @param unknown_type $level
	 * @param unknown_type $message
	 * @param unknown_type $php_error
	 */
	public function write_log($level = 'error', $msg, $php_error = FALSE) {
		if ($this->_sae_enabled === FALSE) {
			return FALSE;
		}
		
		$level = strtoupper($level);
		
		if (!isset($this->_levels[$level]) or ($this->_levels[$level] > $this->_threshold)) {
			return FALSE;
		}
		
		if (class_exists('SaeKV')) {
			sae_set_display_errors(false); // 关闭信息输出
			sae_debug($level . ': ' . $msg); // 记录日志
			sae_set_display_errors(true); // 记录日志后再打开信息输出，否则会阻止正常的错误信息的显示
			return TRUE;
		} else {
			$filepath = $this->_log_path . 'log-' . date('Y-m-d') . '.php';
			$message = '';
			
			if (!file_exists($filepath)) {
				$message .= "<" . "?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?" . ">\n\n";
			}
			
			if (!$fp = @fopen($filepath, FOPEN_WRITE_CREATE)) {
				return FALSE;
			}
			
			$message .= $level . ' ' . (($level == 'INFO') ? ' -' : '-') . ' ' . date($this->_date_fmt) . ' --> ' . $msg . "\n";
			
			flock($fp, LOCK_EX);
			fwrite($fp, $message);
			flock($fp, LOCK_UN);
			fclose($fp);
			
			@chmod($filepath, FILE_WRITE_MODE);
			return TRUE;
		}
	}
	
}
