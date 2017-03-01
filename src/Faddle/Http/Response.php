<?php namespace Faddle\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * HTTP 回应类
 */
class Response implements ResponseInterface {

	private static $_instance;
	public $header = null;
	public $cookie = null;
	public $status = 200;
	protected $protocol_version = '1.1';
	public $content_type = 'text/html';
	public $charset = 'utf-8';
	public $buffer = true;
	
	protected $body = '';
	protected $length = 0;
	protected $issended = false;
	
	public $parse_exec_var = false; //是否对输出再解析变量
	public $send_debug_header = true; //是否发送调试头信息
	public $prepared = false; //是否准备好回应信息，仅供外部使用

	use MessageTrait;
	
	/**
	 * @var string
	 */
	private $reasonPhrase = '';

	/**
	 * @var int
	 */
	private $statusCode = 200;

	/**
	 * @param string|resource|StreamInterface $stream Stream identifier and/or actual stream resource
	 * @param int $status Status code for the response, if any.
	 * @param array $headers Headers for the response, if any.
	 * @throws InvalidArgumentException on any invalid element.
	 */
	public function __construct($body = 'php://memory', $status = 200, array $headers = []) {
		if (! is_string($body) && ! is_resource($body) && ! $body instanceof StreamInterface) {
			throw new InvalidArgumentException(
				'Stream must be a string stream resource identifier, '
				. 'an actual stream resource, '
				. 'or a Psr\Http\Message\StreamInterface implementation'
			);
		}
		$this->stream     = ($body instanceof StreamInterface) ? $body : new Stream($body, 'wb+');
		$this->status($status ? (int) $status : 200);
		list($this->headerNames, $headers) = $this->filterHeaders($headers);
		$this->assertHeaders($headers);
		$this->headers = $headers;
		$this->header = new Header($this->headers);
		$this->cookie = new Cookie();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getStatusCode() {
		return $this->statusCode;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getReasonPhrase() {
		if (! $this->reasonPhrase
			&& HttpStatus::getMessageFromCode($this->statusCode)
		) {
			$this->reasonPhrase = HttpStatus::getMessageFromCode($this->statusCode);
		}
		
		return $this->reasonPhrase;
	}

	/**
	 * {@inheritdoc}
	 */
	public function withStatus($code, $reasonPhrase = '') {
		$new = clone $this;
		$new->status((int) $code);
		$new->reasonPhrase = $reasonPhrase;
		return $new;
	}

	/**
	 * Ensure header names and values are valid.
	 *
	 * @param array $headers
	 * @throws InvalidArgumentException
	 */
	private function assertHeaders(array $headers) {
		foreach ($headers as $name => $headerValues) {
			Header::assertValidName($name);
			array_walk($headerValues, __NAMESPACE__ . '\Header::assertValidValue');
		}
	}

	/** 请求对象 */
	public static function getInstance() {
		if (! self::$_instance)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public static function setInstance(ResponseInterface $response) {
		if (! $response) return;
		if (self::$_instance) self::$_instance = null;
		self::$_instance = $response;
	}
	
	public static function instance() {
		return static::getInstance();
	}
	
	public function header_no_cache() {
		$this->header('Pragma', 'no-cache');
		$this->header('Cache-Control', 'no-store, no-cache');
	}
	
	/**
	 * 设置缓存头($expires: 小时)
	 */
	public function header_cache_public($expires=24) {
		$this->header('Pragma', 'public');
		$this->header('Cache-Control', 'public, maxage=' . intval($expires * 3600));
		$this->header('Expires', gmdate('D, d M Y H:i:s', intval($expires * 3600) + time()) . ' GMT');
	}
	
	public function header_allow_access() {
		$this->header('Access-Control-Allow-Origin', '*');
		$this->header('Access-Control-Allow-Headers', 'X-Requested-With');
		$this->header('Access-Control-Allow-Methods', 'PUT,POST,GET,DELETE,OPTIONS');
	}
	
	public function header($name, $value=null) {
		if (! is_null($value)) {
			$this->header->set($name, $value);
		}
		
		return $this->header->get($name);
	}
	
	public function headers(array $headers=null) {
		if (!empty($headers)) {
			foreach ((array) $headers as $name => $value) {
				$this->header($name, $value);
			}
		}
		list($this->headerNames, $headers) = $this->filterHeaders($this->header->all());
		if (Header::isValid($headers)) $this->headers = $headers;
		return $this->headers;
	}
	
	public function cookie($name, $value=null, $expires=0) {
		if (! is_null($value)) {
			$this->cookie->set($name, $value, $expires);
		}
		
		return $this->cookie->get($name);
	}
	
	public function cookies(array $cookies=null) {
		return $this->cookie->cookies($cookies);
	}
	
	protected function send_headers() {
		//if (headers_sent($file, $line)) {
			//'Headers already sent in ' . $file . ' line ' . $line;
		//}
		$msg = HttpStatus::getMessageFromCode($this->status);
		if (! empty($msg)) $msg = ' '. $msg;
		@header(sprintf('HTTP/%s %d%s', $this->protocol_version, $this->status, $msg), false);
		@header('Content-Type: ' . $this->content_type . '; charset=' . strtolower($this->charset), false);
		$this->header->send();
		$this->cookie->send();
		return $this;
	}
	
	public function status($code=200) {
		if (! is_numeric($code) || is_float($code) || $code < 100 || $code >= 600) {
			trigger_error(sprintf('无效的 Http 状态码：%s'
				, (is_scalar($code) ? $code : gettype($code)), E_USER_WARNING));
			$code = $this->status;
		}
		if (!empty($code)) {
			$this->status = $this->statusCode = intval($code);
		}
		return $this->status;
	}
	
	public function type($type) {
		if (is_string($type) and strpos($type, '/') > 1) {
			$this->content_type = $type;
		}
		return $this->content_type;
	}
	
	public function body($body=null) {
		if (isset($body)) {
			if (! empty($body)) $body = ltrim((string) $body);
			$this->body = $body;
			$this->length = mb_strlen($this->body);
		}
		return $this->body;
	}
	
	public function write($body, $append=true) {
		if ($append) {
			$this->body .= (string) $body;
		} else {
			$this->body = (string) $body;
		}
		$this->length = mb_strlen($this->body);
		
		return $this;
	}

	/**
	 * 设置回应的过期时间
	 *
	 * @param int|string $expires 过期时间
	 * @return object Self
	 */
	public function expires($expires) {
		if ($expires === false) {
			$this->header['Expires'] = 'Mon, 26 Jul 1997 05:00:00 GMT';
			$this->header['Cache-Control'] = array(
				'no-store, no-cache, must-revalidate',
				'post-check=0, pre-check=0',
				'max-age=0'
			);
			$this->header['Pragma'] = 'no-cache';
		} else {
			$expires = is_int($expires) ? $expires : strtotime($expires);
			$this->header['Expires'] = gmdate('D, d M Y H:i:s', $expires) . ' GMT';
			$this->header['Cache-Control'] = 'max-age='.($expires - time());
		}
		
		return $this;
	}

	/**
	 * 发送回应
	 */
	public function send() {
		if (ob_get_length() > 0) ob_end_clean();
		
		if ($vars = $this->parse_exec_var and is_array($vars) and (count($vars) > 0)) {
			$this->body = str_replace(array_keys($vars), array_values($vars), $this->body);
			$this->length = mb_strlen($this->body);
		}
		
		http_response_code($this->status);
		if ($this->send_debug_header) {
			$this->header('x_elapsed_time', round((microtime(true) - floatval(FADDLE_AT)) * 1000, 2).'ms');
			$this->header('x_memory_usage', round(memory_get_usage() / 1024 / 1024, 2).'MB');
			$this->header('x_powered_by', FADDLE_VERSION . ' for PHP/' . PHP_VERSION);
		}
		$this->send_headers();
		
		$output = $this->body;
		
		echo $output;
		
		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		} elseif (strtolower(PHP_SAPI) != 'cli') {
			$level = ob_get_level();
			while (ob_get_level() > 0) ob_end_flush();
		} else {
			flush();
		}
		$this->issended = true;
		
		return $this;
	}

	/**
	 * 输出回应，已回应时将无效
	 */
	public function display($output='') {
		if ($this->issended) return false;
		$this->body($output);
		return $this->send();
	}

	/**
	 * 回应文件
	 * @param string $path 文件位置
	 * @param string $filename 文件名称
	 * @param string $mimetype 文件类型
	 */
	public function file($path, $filename=null, $mimetype=null, $expires=72, $download=true) {
		if (!file_exists($path)) throw new \Exception('file not exists.');
		$this->body('');
		$this->header_cache_public($expires);
		if (null === $filename) {
			$filename = basename($path);
		}
		if (null === $mimetype && function_exists('finfo_file')) {
			$mimetype = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path);
		}
		
		$this->header('Accept-Ranges', 'bytes');
		$this->header('Content-Transfer-Encoding', 'binary');
		if ($mimetype) {
			$this->content_type = $mimetype;
		} else {
			$this->content_type = 'application/octet-stream';
		}
		$this->header('Content-length', filesize($path));
		if ($download) $this->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
		$this->header('Last-Modified', gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
		$this->send();
		
		ob_end_flush();
		readfile($path);
		
		return $this;
	}

	/**
	 * 以 JSON/JSONP 方式回应信息 
	 * @param mixed $object JSON数据
	 * @param string $jsonp_prefix 可设置 JSONP 回调名称
	 * @return self
	 */
	public function json($object, $jsonp_prefix=null) {
		$this->body('');
		$this->header_no_cache();
		$json = json_encode($object, JSON_UNESCAPED_UNICODE);
		
		if (null !== $jsonp_prefix) {
			$callback = trim(strip_tags($jsonp_prefix));
			if (empty($callback)) {
				throw new \InvalidArgumentException('$callback param cannot be empty.');
			}
			$this->content_type = 'application/javascript';
			$this->body("$callback($json);");
		} else {
			$this->content_type = 'application/json';
			$this->body($json);
		}
		
		$this->send();
		
		return $this;
	}

	/**
	 * 重定向
	 */
	public function redirect($url, $status=302) {
		$this->status($status);
		$this->header('Location', $url);
		return $this;
	}

	/**
	 * Publish output content
	 *
	 * @param string|mixed $content 输出内容
	 */
	public function publish($content=null) {
		$fp = fopen("php://output", 'r+'); // Open output stream
		if (is_string($content) || is_numeric($content) || is_null($content)) {
			fputs($fp, $content); // Raw content
		} else {
			if (is_object($content) && method_exists($content, "__toString")) {
				fputs($fp, $content->__toString());
			} else {
				fputs($fp, print_r($content, true));
			}
		}
		//return ob_get_contents();
	}

	/**
	 * 通用接口 respond 方法
	 * @param $code
	 * @param string $message
	 * @param array $data
	 * @param string $type
	 */
	public static function respond($code , $message='', $data=array(), $type='json') {
		//if (!is_numeric($code)) return false;
		if (headers_sent($file, $line)) return false;
		$type = isset($_GET['format']) ? strtolower($_GET['format']) : $type;
		$result = array(
			'code' => $code,
			'message' => $message,
			'data' => $data,
		);
		@header('Pragma: no-cache');
		@header('Cache-Control: no-store, no-cache');
		if ($type == 'json') {
			@header('Content-Type: application/json');
			if (static::$_instance) static::$_instance->content_type = 'application/json';
			echo json_encode($result, JSON_UNESCAPED_UNICODE);
		} else if ($type == 'xml') {
			$xml = \Faddle\Helper\XMLHelper::createXML('result', $result);
			@header('Content-Type: application/xml');
			if (static::$_instance) static::$_instance->content_type = 'application/xml';
			echo $xml;
		} else if ($type == 'html') {
			@header('Content-Type: text/html');
			if (static::$_instance) static::$_instance->content_type = 'text/html';
			$tpl = __DIR__ . '/notice.tpl';
			$title = $code;
			$subtitle = $message;
			if (is_array($data)) {
				$content = $data['content'];
				$page_title = $data['title'];
				$suggestions = $data['actions'];
				$copyright = $data['state'];
			} else $content = strval($data);
			if (file_exists($tpl)) {
				ob_start() and ob_clean();
				include ($tpl);
				echo ob_get_clean();
			} else {
				echo '<h1>' . $code . '</h1><p><strong>' . $message . '</strong></p><p>' . $content . '</p>';
			}
		} else {
			@header('Content-Type: text/plain');
			if (static::$_instance) static::$_instance->content_type = 'text/plain';
			if (!empty($data)) print_r($result);
			else echo $message;
		}
		flush();
		return true;
	}

	/**
	 * 调试输出参数信息 
	 * @param mixed $obj 参数数据
	 * @param mixed $msg 消息文本
	 */
	public static function debug($obj, $msg='', $title='Faddle Debug-Output') {
		if (is_array($obj) || is_object($obj)) {
			$obj = print_r($obj, true);
		} else if (is_file($obj)) {
			$obj = file_get_contents($obj);
		}
		$pre = '<pre>' . htmlentities($obj, ENT_QUOTES) . "</pre>\n";
		$content = '<p>' . $msg . "</p>\n" . $pre;
		$tpl = __DIR__ . '/debug.tpl';
		if (file_exists($tpl)) {
			ob_start() and ob_clean();
			include ($tpl);
			echo ob_get_clean();
		} else {
			echo $content;
		}
		exit;
	}

	/**
	 * 显示消息框
	 * @param mixed $message 消息文本
	 */
	public static function showMessage($message, $url='/', $time=5, $title='提示信息', $htcolor='#537BBC', $bgcolor='#FFF') {
		$goto = "content=\"$time; url=$url\"";
		$info = "将在 <span id='wait'>$time</span> 秒后自动跳转，如不想等待可";
		if (! $time or $time < 0) { //是否自动跳转
			$goto = '';
			$info = '如果需要继续请';
		}
		echo <<<END
<html>
<head>
	<meta http-equiv="refresh" $goto charset="utf-8">
	<meta name="referrer" content="always">
	<meta name="viewport" content="width=device-width">
	<title>$title - Powered by Faddle</title>
	<style>
		body{margin:0;word-wrap:break-word;background-color:$bgcolor;}
		#msgbox{width:480px;border:1px solid #ddd;box-shadow:0 0 8px #ddd;font:14px/22px normal Verdana,微软雅黑;color:#333;margin:0 auto;margin-top:160px;}
		#msgbox .title{background:$htcolor;color:#fff;line-height:40px;height:40px;font-size:15px;padding-left:20px;font-weight:800;}
		#msgbox .message{text-align:center;padding:20px;background-color:#fff;}
		#msgbox .info{text-align:right;padding:6px 10px;border-top:1px solid #ddd;background:#f2f2f2;font-size:12px;color:#888;}
	</style>
</head>
<body>
	<div id="msgbox">
		<div class="title">$title</div>
		<div class="message">$message</div>
		<div class="info">$info <a href="$url">点击这里</a></div>
	</div>
</body>
<script>
(function(){
    var wait = document.getElementById('wait');
    var interval = setInterval(function() {
        var time = --wait.innerHTML;
        if (time <= 0) {
            location.href = '$url';
            clearInterval(interval);
        };
    }, 1000);
})();
</script>
</html>
END;
		exit;
	}

}