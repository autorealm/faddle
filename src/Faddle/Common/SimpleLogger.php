<?php namespace Faddle\Common;

use RuntimeException;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * class for logger
 *
 * @package Faddle\Common
 */
class SimpleLogger extends AbstractLogger {

	private static $_instance;
	
	/**
	 * Minimum log level for the logger
	 *
	 * @access private
	 * @var    string
	 */
	private $level = LogLevel::DEBUG;
	
	private $filename = '';
	private $syslog_ident = 'PHP';
	private $syslog_facility = LOG_USER;

	
	protected function __construct() {
		//cann't new a instance.
	}

	/**
	 * Setup Filelog configuration
	 *
	 * @param  string $filename Output file
	 */
	public static function Filelog($filename='') {
		if (!isset(self::$_instance)) {
			self::$_instance = new self;
		}
		if (! empty($filename) and ! is_file($filename)) {
			@touch($filename);
		}
		$self = self::$_instance;
		$self->filename = $filename;
		return $self;
	}

	/**
	 * Setup Syslog configuration
	 *
	 * @param  string $syslog_ident       Application name
	 * @param  int    $syslog_facility    See http://php.net/manual/en/function.openlog.php
	 */
	public static function Syslog($syslog_ident = 'PHP', $syslog_facility = LOG_USER) {
		if (!isset(self::$_instance)) {
			self::$_instance = new self;
		}
		$self = self::$_instance;
		$self->filename = '';
		$self->syslog_ident = $syslog_ident;
		$self->syslog_facility = $syslog_facility;
		return $self;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param  mixed   $level
	 * @param  string  $message
	 * @param  array   $context
	 */
	public function log($level, $message, array $context = array()) {
		if ($this->getLevelPriority($level) < $this->getLevelPriority($this->getLevel())) {
			return false;
		}
		if (is_file($this->filename) and is_writable($this->filename)) {
			$line = '['.static::get_millisecond_date().'] ['.$level.'] '.$this->interpolate($message, $context).PHP_EOL;
			if (file_put_contents($this->filename, $line, FILE_APPEND | LOCK_EX) === false) {
				throw new RuntimeException('Unable to write to the log file.');
			}
			return true;
		}
		$syslog_priority = $this->getSyslogPriority($level);
		$syslog_message = $this->interpolate($message, $context);
		if (! openlog($this->syslog_ident, LOG_ODELAY | LOG_PID, $this->syslog_facility)) {
			throw new RuntimeException('Unable to connect to syslog.');
		}
		syslog($syslog_priority, $syslog_message);
		return closelog();
	}

	/**
	 * Get syslog priority according to Psr\LogLevel
	 *
	 * @param  mixed  $level
	 * @return integer
	 */
	public function getSyslogPriority($level) {
		switch ($level) {
			case LogLevel::EMERGENCY:
				return LOG_EMERG;
			case LogLevel::ALERT:
				return LOG_ALERT;
			case LogLevel::CRITICAL:
				return LOG_CRIT;
			case LogLevel::ERROR:
				return LOG_ERR;
			case LogLevel::WARNING:
				return LOG_WARNING;
			case LogLevel::NOTICE:
				return LOG_NOTICE;
			case LogLevel::INFO:
				return LOG_INFO;
		}
		
		return LOG_DEBUG;
	}

	/**
	 * Get level priority (same values as Monolog)
	 *
	 * @param  mixed  $level
	 * @return integer
	 */
	public function getLevelPriority($level) {
		switch ($level) {
			case LogLevel::EMERGENCY:
				return 600;
			case LogLevel::ALERT:
				return 550;
			case LogLevel::CRITICAL:
				return 500;
			case LogLevel::ERROR:
				return 400;
			case LogLevel::WARNING:
				return 300;
			case LogLevel::NOTICE:
				return 250;
			case LogLevel::INFO:
				return 200;
		}

		return 100;
	}

	/**
	 * Set minimum log level
	 *
	 * @access public
	 * @param  string  $level
	 */
	public function setLevel($level) {
		$this->level = $level;
	}

	/**
	 * Get minimum log level
	 *
	 * @access public
	 * @return string
	 */
	public function getLevel() {
		return $this->level;
	}

	/**
	 * Dump to log a variable (by example an array)
	 *
	 * @param mixed $variable
	 */
	public function dump($variable) {
		$this->log(LogLevel::DEBUG, var_export($variable, true));
	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @access protected
	 * @param  string $message
	 * @param  array $context
	 * @return string
	 */
	protected function interpolate($message, array $context = array()) {
		if (is_array($message)) {
			return json_encode($message, JSON_UNESCAPED_UNICODE);
		} elseif ($message instanceof \Exception) {
			$message = sprintf('<b>[$s] $s</b>' ."\n". '<p>%s</p>' ."\n". '<i>$s (%s)</i>' ."\n". '<pre>%s</pre>',
				$message->getCode(), get_class($message), $message->getMessage(), $message->getFile(),
				$message->getLine(), $message->getTraceAsString()
			);
		}
		// build a replacement array with braces around the context keys
		$replace = array();
		
		foreach ($context as $key => $val) {
			$replace['{' . $key . '}'] = $val;
		}
		
		// interpolate replacement values into the message and return
		return strtr($message, $replace);
	}
	
	public static function get_millisecond_date() {
		list($usec, $sec) = explode(' ', microtime());
		$msec = sprintf('%03d', round($usec*1000));
		return date('Y-m-d H:i:s.' . $msec . '');
	}

}

/* -- sample php code --

require '../autoload.php';

// Setup File logging
$logger = new Faddle\Common\SimpleLogger::Filelog('/tmp/simplelogger.log');

// Output to the file: "[2013-06-02 16:03:28] [info] boo"
$logger->info('boo');

// Output to the file: "[2013-06-02 16:03:28] [error] Error at /Users/fred/Devel/libraries/simpleLogger/example.php at line 24"
$logger->error('Error at {filename} at line {line}', array('filename' => __FILE__, 'line' => __LINE__));

// Dump a variable
$values = array(
    'key' => 'value'
);

// Output: [2013-06-02 16:05:32] [debug] array (
//  'key' => 'value',
// )
$logger->dump($values);

// Setup Syslog logging
$logger = new Faddle\Common\SimpleLogger::Syslog('myapp');

// Output to syslog: "Jun  2 15:55:09 hostname myapp[2712]: boo"
$logger->error('boo');

// Output to syslog: "Jun  2 15:55:09 hostname myapp[2712]: Error at /Users/Me/Devel/libraries/simpleLogger/example.php at line 15"
$logger->error('Error at {filename} at line {line}', ['filename' => __FILE__, 'line' => __LINE__]);

*/