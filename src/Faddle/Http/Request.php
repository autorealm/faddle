<?php namespace Faddle\Http;

use Psr\Http\Message\RequestInterface;

/**
 * HTTP 请求类
 */
class Request implements RequestInterface {

	const OVERRIDE = 'HTTP_X_HTTP_METHOD_OVERRIDE';
	
	protected static $proxies = array(); //受信任的IP地址数组
	protected static $resolvers = array(); //请求分解数组
	protected static $input = array(); //请求数据
	public static $data = array(); //附加数据
	
	private static $_instance = null;
	
	use MessageTrait, RequestTrait;

	/**
	 * @param null|string $uri URI for the request, if any.
	 * @param null|string $method HTTP method for the request, if any.
	 * @param string|resource|StreamInterface $body Message body, if any.
	 * @param array $headers Headers for the message, if any.
	 * @throws InvalidArgumentException for any invalid value.
	 */
	public function __construct($uri = null, $method = null, $body = 'php://memory', array $headers = []) {
		$this->initialize($uri, $method, $body, $headers);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeaders() {
		$headers = $this->headers;
		if (! $this->hasHeader('host')
			&& ($this->uri && $this->uri->getHost())
		) {
			$headers['Host'] = [$this->getHostFromUri()];
		}
		return $headers;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHeader($header) {
		if (! $this->hasHeader($header)) {
			if (strtolower($header) === 'host'
				&& ($this->uri && $this->uri->getHost())
			) {
				return [$this->getHostFromUri()];
			}
			return [];
		}
		$header = $this->headerNames[strtolower($header)];
		$value = $this->headers[$header];
		$value = is_array($value) ? $value : [$value];
		return $value;
	}
	
	private function __clone() {}

	/**
	 * 单例获取类实例
	 */
	public static function getInstance() {
		if(! (self::$_instance instanceof self) ) {
			$params = static::params();
			self::$_instance = new self(new Uri($params['url']), $params['method']);
		}
		return self::$_instance;
	}
	
	public static function instance() {
		return self::getInstance();
	}

	/**
	 * 返回（设置）额外请求数据
	 * @param string $data 需要设置的额外数据
	 * @param string $overwrite 是否覆盖
	 */
	public static function data($data=null, $overwrite=false) {
		if (!is_null($data)) {
			if ($overwrite)
				self::$data = $data;
			else
				self::$data = array_merge(self::$data, (array) $data);
		}
		return self::$data;
	}

	/**
	 * 返回请求参数数组
	 */
	public static function params() {
		static $params;
		if (! empty($params) and is_array($params)) return $params;
		$proxy_ip = '';
		static $forwarded = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED'
		);
		$flags = \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;
		foreach ($forwarded as $key) {
			if (array_key_exists($key, $_SERVER)) {
				sscanf($_SERVER[$key], '%[^,]', $_ip);
				if (filter_var($_ip, \FILTER_VALIDATE_IP, $flags) !== false) {
					$proxy_ip = $_ip;
					break;
				}
			}
		};
		
		$secure = getenv('HTTPS') && (strtolower('HTTPS') != 'off');
		$host = getenv('HTTP_HOST') ?: getenv('SERVER_NAME');
		$uri = getenv('REQUEST_URI') ?: '/';
		$query = array();
		parse_str(getenv('QUERY_STRING') ?: '', $query);
		return $params = array(
				'host' => $host,
				'path' => parse_url($uri, PHP_URL_PATH),
				'query' => $query,
				'url' => ($secure?'https':'http').'://'.$host.getenv('REQUEST_URI'),
				'uri' => $uri,
				'base' => str_replace(array('\\',' '), array('/','%20'), dirname(getenv('SCRIPT_NAME'))),
				'method' => getenv('HTTP_X_HTTP_METHOD_OVERRIDE') ?: (getenv('REQUEST_METHOD') ?: 'GET'),
				'referer' => getenv('HTTP_REFERER') ?: '',
				'ip' => getenv('REMOTE_ADDR') ?: $proxy_ip,
				'ajax' => getenv('HTTP_X_REQUESTED_WITH') == 'XMLHttpRequest',
				'scheme' => getenv('SERVER_PROTOCOL') ?: 'HTTP/1.1',
				'accept' => getenv('HTTP_ACCEPT') ?: '',
				'language' => getenv('HTTP_ACCEPT_LANGUAGE') ?: '',
				'user_agent' => getenv('HTTP_USER_AGENT') ?: '',
				'body' => file_get_contents('php://input') ?: '',
				'type' => getenv('CONTENT_TYPE') ?: '',
				'length' => getenv('CONTENT_LENGTH') ?: 0,
				'secure' => $secure,
				'proxy_ip' => $proxy_ip
			);
	}
	
	public static function path() {
		return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	}
	
	/**
	 * 返回 Accept 请求内容格式数据，或判断是否为某内容格式
	 * @param string $type 需判断的内容格式
	 */
	public static function accept($type=null) {
		$accept = getenv('HTTP_ACCEPT') ?: '';
		if (is_null($type)) return $accept;
		$accepts = explode(';', $accept);
		$accept = explode(',', $accepts[0]);
		array_map('trim', $accept);
		array_map('strtolower', $accept);
		if (in_array(strtolower($type), $accept)) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * 返回请求内容语言，或判断是否包含某语言
	 * @param string $lang 需判断的语言
	 * @return null 无语言请求，0-1 用户语言喜好
	 */
	public static function language($lang=null) {
		$accept = getenv('HTTP_ACCEPT_LANGUAGE') ?: '';
		if (empty($accept)) return null;
		$_accepts = explode(';', $accept);
		$accepts = array();
		foreach ($_accepts as $accept) {
			$accept = explode(',', $accept);
			if (preg_match('/^q=[0-9\.]+$/i', $accept[0])) {
				$q = strval(substr($accept[0], 2));
				unset($accept[0]);
			} else {
				$q = '1.0';
			}
			$accepts[$q] = $accept;
		}
		if (is_null($lang)) return $accepts;
		foreach ($accepts as $q => $accept) {
			array_map('trim', $accept);
			array_map('strtolower', $accept);
			if (in_array(strtolower($lang), $accept)) {
				return floatval($q);
			}
		}
		return 0;
	}

	/**
	 * 获取请求参数
	 */
	public static function request($key, $default=null) {
		return static::lookup($_REQUEST, $key, $default);
	}

	/**
	 * 返回一个 GET 请求数据
	 * @param string $key
	 * @param string $default
	 */
	public static function get($key=null, $default=null) {
		return static::lookup($_GET, $key, $default);
	}

	/**
	 * 返回一个 POST 请求数据
	 * @param string $key
	 * @param string $default
	 */
	public static function post($key=null, $default=null) {
		return static::lookup($_POST, $key, $default);
	}

	/**
	 * 返回一个 STREAM 请求数据
	 * @param string $key
	 * @param string $default
	 */
	protected static function stream($key, $default) {
		if (Request::overridden())
			return static::lookup($_POST, $key, $default);
	
		parse_str(file_get_contents('php://input'), $input);
		return static::lookup($input, $key, $default);
	}

	/**
	 * 返回一个 PUT 请求数据
	 */
	public static function put($key=null, $default=null) {
		return static::method() === 'PUT' ?
		static::stream($key, $default) : $default;
	}

	/**
	 * 返回一个 DELETE 请求数据
	 */
	public static function delete($key=null, $default=null) {
		return static::method() === 'DELETE' ?
		static::stream($key, $default) : $default;
	}

	/**
	 * 返回一个请求数据
	 */
	public static function input($key=null, $default=null) {
		return static::lookup(static::submitted(), $key, $default);
	}

	/**
	 * 获取请求的文件
	 */
	public static function files($key=null, $default=null) {
		return static::lookup($_FILES, $key, $default);
	}

	/**
	 * 判断是否包含该键
	 */
	public static function has($keys) {
		foreach ((array) $keys as $key) {
			if (trim(static::input($key)) == '') return FALSE;
		}
		return TRUE;
	}

	/**
	 * 返回仅包含指定键的请求数组
	 */
	public static function only($keys) {
		return array_intersect_key(
				static::input(), array_flip((array) $keys)
		);
	}

	/**
	 * 返回不包含指定键的请求数组
	 */
	public static function except($keys) {
		return array_diff_key(
				static::input(), array_flip((array) $keys)
		);
	}

	/**
	 * 获取请求方式
	 */
	public static function method() {
		$method = static::overridden() ? (isset($_POST[static::OVERRIDE]) ?
				$_POST[static::OVERRIDE] : $_SERVER[static::OVERRIDE]) :
				$_SERVER['REQUEST_METHOD'];
		return strtoupper($method);
	}

	/**
	 * 是否指定的请求方式
	 */
	public static function is($method) {
		if (is_null($method)) return false;
		foreach ((array) $method as $rm) {
			if (strtoupper($rm) == self::method())
				return true;
		}
		return false;
	}

	/**
	 * 获取 SESSION 值
	 */
	public static function session($key=null, $default=null) {
		return static::lookup($_SESSION, $key, $default);
	}

	/**
	 * 获取 COOKiE 值
	 */
	public static function cookie($key=null, $default=null) {
		return static::lookup($_COOKIE, $key, $default);
	}

	/**
	 * 获取 SERVER 值
	 */
	public static function server($key=null, $default=null) {
		return static::lookup($_SERVER, $key, $default);
	}

	/**
	 * 获取 HEADER 值
	 */
	public static function header($key, $default=null) {
		$key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
		return static::lookup($_SERVER, $key, $default);
	}

	/**
	 * 获取用户代理字符串
	 */
	public static function agent($default=null) {
		return static::server('HTTP_USER_AGENT', $default);
	}
	
	public static function resolvers($resolvers=array()) {
		if ($resolvers || empty(static::$resolvers)) {
			static::$resolvers = $resolvers +
			array(
					'PATH_INFO',
					'REQUEST_URI' => function($uri) {
						return parse_url($uri, PHP_URL_PATH);
					},
					'PHP_SELF',
					'REDIRECT_URL'
				);
		}
		return static::$resolvers;
	}

	/**
	 * 获取请求完整URL地址
	 */
	public static function url() {
		return static::scheme(TRUE).static::host()
		.static::port(TRUE).static::query(TRUE);
	}

	/**
	 * 获取请求URI字符串
	 */
	public static function uri() {
		foreach (static::resolvers() as $key => $resolver) {
			$key = is_numeric($key) ? $resolver : $key;
			if (isset($_SERVER[$key])) {
				if (is_callable($resolver)) {
					$uri = $resolver($_SERVER[$key]);
					if ($uri !== FALSE) return $uri;
				} else {
					return $_SERVER[$key];
				}
			}
		}
	}

	/**
	 * 获取请求URI字符串
	 */
	public static function path_info() {
		$path_info = '/';
		if (! empty($_SERVER['PATH_INFO'])) {
			$path_info = $_SERVER['PATH_INFO'];
		} elseif (! empty($_SERVER['ORIG_PATH_INFO']) && $_SERVER['ORIG_PATH_INFO'] !== '/index.php') {
			$path_info = $_SERVER['ORIG_PATH_INFO'];
		} else {
			if (! empty($_SERVER['REQUEST_URI'])) {
				$path_info = (strpos($_SERVER['REQUEST_URI'], '?') > 0) 
					? strstr($_SERVER['REQUEST_URI'], '?', true) : $_SERVER['REQUEST_URI'];
			}
		}
		return $path_info;
	}

	/**
	 * 获取请求查询数组
	 */
	public static function query($key=null, $value=null) {
		$request_uri = static::server('REQUEST_URI', '/');
		
		if ($key !== NULL && !empty($key)) {
			$query = array();
			parse_str(static::server('QUERY_STRING', ''), $query);
			if (is_array($key)) {
				$query = array_merge($query, $key);
			} elseif (isset($value)) {
				$query[$key] = $value;
			}
			if (strpos($request_uri, '?') !== false) {
				$request_uri = strstr($request_uri, '?', true);
			}
			
			return $request_uri . (!empty($query) ? '?' . http_build_query($query) : null);
		}
		
		return parse_url($request_uri, PHP_URL_QUERY);
	}

	/**
	 * 获取请求内容类型
	 */
	public static function type($default=null, $strict=false) {
		$type = static::server('HTTP_CONTENT_TYPE',
				$default ?: 'application/x-www-form-urlencoded');
		if ($strict) return $type;
	
		$types = preg_split('/\s*;\s*/', $type);
		return $types;
	}

	/**
	 * 返回请求协议
	 */
	public static function scheme($decorated=false) {
		$scheme = static::secure() ? 'https' : 'http';
		return $decorated ? "$scheme://" : $scheme;
	}

	/**
	 * 是否为安全(HTTPS)连接
	 */
	public static function secure() {
		if (strtoupper(static::server('HTTPS')) == 'ON')
			return TRUE;
	
		if (!static::entrusted()) return FALSE; //非信任站点
	
		return (strtoupper(static::server('SSL_HTTPS')) == 'ON' ||
				strtoupper(static::server('X_FORWARDED_PROTO')) == 'HTTPS');
	}

	/**
	 * 获取一个请求路径分段值
	 */
	public static function segment($index, $default=null) {
		$segments = explode('/', trim(parse_url(static::server('REQUEST_URI', '/'), PHP_URL_PATH) ?: array(), '/'));;
		
		if ($index < 0) {
			$index *= -1;
			$segments = array_reverse($segments);
		}
		
		return static::lookup($segments, $index - 1, $default);
	}

	/**
	 * 获取当前主机域名
	 */
	public static function host($default=null) {
		$keys = array('HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR');
		
		if (static::entrusted() &&
				$host = static::server('X_FORWARDED_HOST')) {
					$host = explode(',', $host);
					$host = trim($host[count($host) - 1]);
				} else {
					foreach($keys as $key) {
						if (isset($_SERVER[$key])) {
							$host = $_SERVER[$key];
							break;
						}
					}
				}
			
				return isset($host) ?
				preg_replace('/:\d+$/', '', $host) : $default;
	}

	/**
	 * 获取客户IP地址
	 */
	public static function ip($trusted=true) {
		$keys = array(
				'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
				'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'
		);
		
		$ips = array();
		
		if ($trusted && isset($_SERVER['HTTP_CLIENT_IP']))
			$ips[] = $_SERVER['HTTP_CLIENT_IP'];
		
		foreach ($keys as $key) {
			if (isset($_SERVER[$key])) {
				if (static::entrusted()) {
					$parts = explode(',', $_SERVER[$key]);
					$ips[] = trim($parts[count($parts) - 1]);
				}
			}
		}
		
		foreach ($ips as $ip) {
			if (filter_var($ip, FILTER_VALIDATE_IP,
					FILTER_FLAG_IPV4 || FILTER_FLAG_IPV6 ||
					FILTER_FLAG_NO_PRIV_RANGE || FILTER_FLAG_NO_RES_RANGE)) {
						return $ip;
					}
		}
		
		return static::server('REMOTE_ADDR', '0.0.0.0');
	}

	/**
	 * 获取请求体
	 * @param string $default
	 */
	public static function body($default=null) {
		return file_get_contents('php://input') ?: $default;
	}
	
	protected static function overridden() {
		return isset($_POST[static::OVERRIDE]) || isset($_SERVER[static::OVERRIDE]);
	}
	
	public static function proxies($proxies) {
		static::$proxies = (array) $proxies;
	}
	
	public static function entrusted() {
		return (empty(static::$proxies) || isset($_SERVER['REMOTE_ADDR'])
				&& in_array($_SERVER['REMOTE_ADDR'], static::$proxies));
	}

	/**
	 * 合并请求数组
	 * @return multitype:|number
	 */
	protected static function submitted() {
		if (!empty(static::$input)) return static::$input;
	
		parse_str(static::body(), $input);
		return static::$input = (array) $_GET + (array) $_POST + (array) $input;
	}

	/**
	 * 获取一个全局数据中的数据
	 */
	protected static function lookup($array, $key, $default) {
		if ($key === NULL) return $array;
		return isset($array[$key]) ? $array[$key] : $default;
	}

	/**
	 * 获取请求端口
	 */
	public static function port($decorated=false) {
		$port = static::entrusted() ?
		static::server('X_FORWARDED_PORT') : NULL;
		
		$port = $port ?: static::server('SERVER_PORT');
		
		return $decorated ? (
				in_array($port, array(80, 443)) ? '' : ":$port"
		) : $port;
	}

}