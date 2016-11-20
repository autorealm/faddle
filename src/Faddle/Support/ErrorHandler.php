<?PHP namespace Faddle\Support;

if (! defined('ERROR_LOG_FILE')) define('ERROR_LOG_FILE', 'errors.log');

/**
 * 错误处理器
 */
class ErrorHandler {

	public static $errors = array();
	
	public static $errortype = array (
			E_ERROR              => 'Error',
			E_WARNING            => 'Warning',
			E_PARSE              => 'Parsing Error',
			E_NOTICE             => 'Notice',
			E_CORE_ERROR         => 'Core Error',
			E_CORE_WARNING       => 'Core Warning',
			E_COMPILE_ERROR      => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR         => 'User Error',
			E_USER_WARNING       => 'User Warning',
			E_USER_NOTICE        => 'User Notice',
			E_STRICT             => 'Runtime Notice',
			E_RECOVERABLE_ERROR   => 'Catchable Fatal Error'
	);
	
	public static $user_errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
	
	public function __construct() {
		
	}
	
	public static function errors() {
		return self::$errors;
	}
	
	public static function get_last_error() {
		$last = count(self::$errors) - 1;
		if ($last >= 0)
			return self::$errors[$last];
		else
			return null;
	}

	public static function get_json_last_error() {
		$errorCode = json_last_error();
		switch ($errorCode) {
			case JSON_ERROR_NONE:
				return false;
				break;
			case JSON_ERROR_DEPTH:
				$msg = 'Maximum stack depth exceeded';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				$msg = 'Underflow or the modes mismatch';
				break;
			case JSON_ERROR_CTRL_CHAR:
				$msg = 'Unexpected control character found';
				break;
			case JSON_ERROR_SYNTAX:
				$msg = 'Syntax error, malformed JSON';
				break;
			case JSON_ERROR_UTF8:
				$msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
			default:
				$msg = 'Unknown error';
				break;
		}
		return array(
			'code' => $errorCode,
			'message' => $msg
		);
	}

	public static function get_millisecond($float=false) {
		list($usec, $sec) = explode(' ', microtime());
		if ($float) {
			return ((float)$usec + (float)$sec);
		} else {
			$msec = round($usec*1000);
			return $msec;
		}
	}
	
	public static function handle($errno, $errmsg, $filename, $line, $vars=null) {
		$time = date("Y-m-d H:i:s." . self::get_millisecond() . " (T)");
		
		$error = array(
				'time' => $time,
				'error' => isset(self::$errortype[$errno]) ? self::$errortype[$errno] : 'Unknown error',
				'type' => $errno,
				'message' => $errmsg,
				'file' => $filename,
				'line' => $line,
				//'vars' => $vars
		);
		
		self::$errors[] = $error;
	}
	
	public static function handlex(Exception $e) {
		$msg = sprintf('<h1>500 Internal Server Error</h1>'. "\n". '<h3>%s (%s)</h3>'. "\n". '<pre>%s</pre>',
				$e->getMessage(), $e->getCode(), $e->getTraceAsString()
			);
		self::log($msg);
	}
	
	public static function deal($errno, $errmsg, $filename, $line, $vars) {
		$self = new self();
		switch($errno) {
			case E_USER_ERROR :
				return $self->dealError('致命错误', $filename, $errmsg, $line);
				break;
			case E_USER_WARNING:
			case E_WARNING:
				return $self->dealError('警告错误', $filename, $errmsg, $line);
				break;
			case E_NOTICE:
			case E_USER_NOTICE:
				return $self->dealError('通知错误', $filename, $errmsg, $line);
				break;
			default:
				return false;
		}
	}
	
	/**
	 * 错误处理
	 */
	public function dealError($title, $filename, $message, $line) {
		ob_start();
		debug_print_backtrace();
		$backtrace = ob_get_flush();
		$datetime = date('Y-m-d H:i:s', time());
		$errorMsg = <<<EOF
[{$title}]
时间：{$datetime}
文件：{$filename}
信息：{$message}
行号：{$line}
追踪：
{$backtrace}

EOF;
		
		return self::log($errorMsg);
	}
	
	public static function log($msg) {
		return error_log($msg.PHP_EOL, 3, ERROR_LOG_FILE);
	}
	
	public static function json_handle_error($errno, $errmsg, $filename, $linenum, $vars) {
		$dt = date("Y-m-d H:i:s." . self::get_millisecond() . " (T)");
		$err = array();
		$err['time'] = $dt;
		$err['type'] = $errno;
		$err['error'] = self::$errortype[$errno];
		$err['message'] = $errmsg;
		$err['file'] = $filename;
		$err['line'] = $linenum;
		if (in_array($errno, self::$user_errors)) {
			$err['vars'] = serialize($vars, true);
		}
		echo $err;
	}
	
	public static function html_handle_error($errno, $errmsg, $filename, $linenum, $vars) {
		$dt = date("Y-m-d H:i:s." . self::get_millisecond() . " (T)");
		$err = "<ErrorEntry style='display:none;'>\n";
		$err .= "<DateTime>$dt</DateTime>\n";
		$err .= "<ErrorNum>$errno</ErrorNum>\n";
		$err .= "<ErrorType>". self::$errortype[$errno] ."</ErrorType>\n";
		$err .= "<ErrorMsg>$errmsg</ErrorMsg>\n";
		$err .= "<ScriptName>$filename</ScriptName>\n";
		$err .= "<ScriptLineNum>$linenum</ScriptLineNum>\n";
		// set of errors for which a var trace will be saved
		if (in_array($errno, self::$user_errors)) {
			if (function_exists('wddx_serialize_value'))
				$err .= "<VarTrace>" . wddx_serialize_value($vars, "Variables") . "</VarTrace>\n";
			else
				$err .= "<VarTrace>\n" .var_export($vars, true) . "\n</VarTrace>\n";
		}
		$err .= "</ErrorEntry>";
		$err = "\r\n" . $err . "\r\n";
		echo $err;
	}
	
}


//set_error_handler(array('ErrorHandler', 'deal'));

