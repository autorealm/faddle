<?php namespace Faddle\Helper;

use Faddle\Http\Header;
use Faddle\Helper\UriHelper as Uri;
use Exception;

/**
 * HTTP ÇëÇóÖúÊÖÀà
 * @since 2015-11
 */
class RequestHelper {
	protected $baseUri;
	public $header = null;
	protected $respHeaders = array();
	const VERSION = '0.2.1';

	public function __construct() {
		$this->baseUri = new Uri();
		$this->header = array();
	}

	public static function getProvider($curl=true) {
		if ($curl and CurlHelper::isAvailable()) {
			return new CurlHelper();
		}
		
		if (StreamHelper::isAvailable()) {
			return new StreamHelper();
		}
		
		throw new Exception('There isn\'t any available provider');
	}

	public function setHeader($name, $value) {
		$this->header[] = sprintf('%s: %s', $name, $value);
	}

	public function removeHeader($name) {
		foreach($this->header as $k => $v) {
			if (strtolower($k) == strtolower($name)) unset($this->header[$k]);
		}
	}

	public function setBaseUri($baseUri) {
		$this->baseUri = new Uri($baseUri);
	}

	public function getBaseUri() {
		return $this->baseUri;
	}

	public function resolveUri($uri) {
		return $this->baseUri->resolve($uri);
	}

	public function getResponseHeaders() {
		return $this->respHeaders;
	}

}

class CurlHelper extends RequestHelper {
	private $handle = null;

	public static function isAvailable() {
		return extension_loaded('curl');
	}

	public function __construct() {
		if (!self::isAvailable()) {
			throw new Exception('CURL extension is not loaded');
		}
		
		$this->handle = curl_init();
		$this->initOptions();
		parent::__construct();
	}

	public function __destruct() {
		curl_close($this->handle);
	}

	public function __clone() {
		$request = new self();
		$request->handle = curl_copy_handle($this->handle);
		
		return $request;
	}

	private function initOptions() {
		$this->setOptions(array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_AUTOREFERER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 20,
				CURLOPT_HEADER => true,
				CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				CURLOPT_USERAGENT => 'Faddle HTTP-Request/' . self::VERSION . ' (Curl)',
				CURLOPT_CONNECTTIMEOUT => 30,
				CURLOPT_TIMEOUT => 30 
		));
	}

	public function setOption($option, $value) {
		return curl_setopt($this->handle, $option, $value);
	}

	public function setOptions($options) {
		return curl_setopt_array($this->handle, $options);
	}

	public function setTimeout($timeout) {
		$this->setOption(CURLOPT_TIMEOUT, $timeout);
	}

	public function setConnectTimeout($timeout) {
		$this->setOption(CURLOPT_CONNECTTIMEOUT, $timeout);
	}

	public function reset() {
		//curl_reset($this->handle);
		$this->handle = curl_init();
		$this->initOptions();
		$this->header = array();
	}

	private function send($customHeader = array(), $fullResponse = false) {
		if (!empty($customHeader)) {
			$header = $customHeader;
		} else {
			$header = array();
			if (count($this->header) > 0) {
				$header = $this->header;
			}
			$header[] = 'Expect:';
		}
		
		$this->setOption(CURLOPT_HTTPHEADER, $header);
		
		$content = curl_exec($this->handle);
		
		if ($errno = curl_errno($this->handle)) {
			throw new Exception(curl_error($this->handle), $errno);
		}
		
		$headerSize = curl_getinfo($this->handle, CURLINFO_HEADER_SIZE);
		
		$this->respHeaders = Header::parse(substr($content, 0, $headerSize));
		
		if ($fullResponse) {
			$body = $content;
		} else {
			$body = substr($content, $headerSize);
		}
		
		return $body;
	}

	/**
	 * Prepare data for a cURL post.
	 *
	 * @param mixed   $params      Data to send.
	 * @param boolean $useEncoding Whether to url-encode params. Defaults to true.
	 *
	 * @return void
	 */
	private function initPostFields($params, $useEncoding = true) {
		if (is_array($params)) {
			foreach($params as $param) {
				if (is_string($param) && preg_match('/^@/', $param)) {
					$useEncoding = false;
					break;
				}
			}
			
			if ($useEncoding) {
				$params = http_build_query($params);
			}
		}
		
		if (!empty($params)) {
			$this->setOption(CURLOPT_POSTFIELDS, $params);
		}
	}

	public function setProxy($host, $port = 8080, $user = null, $pass = null) {
		$this->setOptions(array(
				CURLOPT_PROXY => $host,
				CURLOPT_PROXYPORT => $port 
		));
		
		if (!empty($user) && is_string($user)) {
			$pair = $user;
			if (!empty($pass) && is_string($pass)) {
				$pair .= ':' . $pass;
			}
			$this->setOption(CURLOPT_PROXYUSERPWD, $pair);
		}
	}

	public function get($uri, $params = array(), $customHeader = array(), $fullResponse = false) {
		$uri = $this->resolveUri($uri);
		
		if (!empty($params)) {
			$uri->extendQuery($params);
		}
		
		$this->setOptions(array(
				CURLOPT_URL => $uri->build(),
				CURLOPT_HTTPGET => true,
				CURLOPT_CUSTOMREQUEST => 'GET' 
		));
		
		return $this->send($customHeader, $fullResponse);
	}

	public function head($uri, $params = array(), $customHeader = array(), $fullResponse = false) {
		$uri = $this->resolveUri($uri);
		
		if (!empty($params)) {
			$uri->extendQuery($params);
		}
		
		$this->setOptions(array(
				CURLOPT_URL => $uri->build(),
				CURLOPT_HTTPGET => true,
				CURLOPT_CUSTOMREQUEST => 'HEAD' 
		));
		
		return $this->send($customHeader, $fullResponse);
	}

	public function delete($uri, $params = array(), $customHeader = array(), $fullResponse = false) {
		$uri = $this->resolveUri($uri);
		
		if (!empty($params)) {
			$uri->extendQuery($params);
		}
		
		$this->setOptions(array(
				CURLOPT_URL => $uri->build(),
				CURLOPT_HTTPGET => true,
				CURLOPT_CUSTOMREQUEST => 'DELETE' 
		));
		
		return $this->send($customHeader, $fullResponse);
	}

	public function post($uri, $params = array(), $useEncoding = true, $customHeader = array(), $fullResponse = false) {
		$this->setOptions(array(
				CURLOPT_URL => $this->resolveUri($uri),
				CURLOPT_POST => true,
				CURLOPT_CUSTOMREQUEST => 'POST' 
		));
		
		$this->initPostFields($params, $useEncoding);
		
		return $this->send($customHeader, $fullResponse);
	}

	public function put($uri, $params = array(), $useEncoding = true, $customHeader = array(), $fullResponse = false) {
		$this->setOptions(array(
				CURLOPT_URL => $this->resolveUri($uri),
				CURLOPT_POST => true,
				CURLOPT_CUSTOMREQUEST => 'PUT' 
		));
		
		$this->initPostFields($params, $useEncoding, $customHeader);
		
		return $this->send($customHeader, $fullResponse);
	}

}

class StreamHelper extends RequestHelper {
	private $context = null;

	public static function isAvailable() {
		$wrappers = stream_get_wrappers();
		
		return in_array('http', $wrappers) && in_array('https', $wrappers);
	}

	public function __construct() {
		if (!self::isAvailable()) {
			throw new Exception('HTTP or HTTPS stream wrappers not registered');
		}
		
		$this->context = stream_context_create();
		$this->initOptions();
		parent::__construct();
	}

	public function __destruct() {
		$this->context = null;
	}

	private function initOptions() {
		$this->setOptions(array(
				'user_agent' => 'Faddle HTTP-Request/' . self::VERSION . ' (Stream)',
				'follow_location' => 1,
				'max_redirects' => 20,
				'timeout' => 30 
		));
	}

	public function setOption($option, $value) {
		return stream_context_set_option($this->context, 'http', $option, $value);
	}

	public function setOptions($options) {
		return stream_context_set_option($this->context, array(
				'http' => $options 
		));
	}

	public function setTimeout($timeout) {
		$this->setOption('timeout', $timeout);
	}

	public function reset() {
		$this->context = stream_context_create();
		$this->initOptions();
		$this->header = array();
	}

	private function errorHandler($errno, $errstr) {
		throw new Exception($errstr, $errno);
	}

	private function send($uri) {
		if (count($this->header) > 0) {
			$headers = '';
			foreach ($this->header as $header) {
				$headers .= $header . "\r\n";
			}
			$this->setOption('header', $headers);
		}
		
		set_error_handler(array(
				$this,
				'errorHandler' 
		));
		$content = file_get_contents($uri->build(), false, $this->context);
		restore_error_handler();
		
		$this->respHeaders = Header::parse($http_response_header);
		
		return $content;
	}

	private function initPostFields($params) {
		if (!empty($params) && is_array($params)) {
			$this->setHeader('Content-Type', 'application/x-www-form-urlencoded');
			$this->setOption('content', http_build_query($params));
		}
	}

	public function setProxy($host, $port = 8080, $user = null, $pass = null) {
		$uri = new Uri(array(
				'scheme' => 'tcp',
				'host' => $host,
				'port' => $port 
		));
		
		if (!empty($user)) {
			$uri->user = $user;
			if (!empty($pass)) {
				$uri->pass = $pass;
			}
		}
		
		$this->setOption('proxy', $uri->build());
	}

	public function get($uri, $params = array()) {
		$uri = $this->resolveUri($uri);
		
		if (!empty($params)) {
			$uri->extendQuery($params);
		}
		
		$this->setOptions(array(
				'method' => 'GET',
				'content' => '' 
		));
		
		$this->removeHeader('Content-Type');
		
		return $this->send($uri);
	}

	public function head($uri, $params = array()) {
		$uri = $this->resolveUri($uri);
		
		if (!empty($params)) {
			$uri->extendQuery($params);
		}
		
		$this->setOptions(array(
				'method' => 'HEAD',
				'content' => '' 
		));
		
		$this->removeHeader('Content-Type');
		
		return $this->send($uri);
	}

	public function delete($uri, $params = array()) {
		$uri = $this->resolveUri($uri);
		
		if (!empty($params)) {
			$uri->extendQuery($params);
		}
		
		$this->setOptions(array(
				'method' => 'DELETE',
				'content' => '' 
		));
		
		$this->removeHeader('Content-Type');
		
		return $this->send($uri);
	}

	public function post($uri, $params = array()) {
		$this->setOption('method', 'POST');
		
		$this->initPostFields($params);
		
		return $this->send($this->resolveUri($uri));
	}

	public function put($uri, $params = array()) {
		$this->setOption('method', 'PUT');
		
		$this->initPostFields($params);
		
		return $this->send($this->resolveUri($uri));
	}

}