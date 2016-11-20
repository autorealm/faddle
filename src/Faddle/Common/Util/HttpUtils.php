<?PHP namespace Faddle\Common\Util;

class HttpUtils {

	/**
	 * 获取 refer URL 地址
	 * @return Ambigous <string, unknown>
	 */
	public static function refer_url(){
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	}
	
	/**
	 * 获取当前页面的 URL 地址
	 * @return string
	 */
	public static function this_url(){
		$s_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http';
		$s_url .= '://';
		return $s_url . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
	}
	
	/**
	 * 获取客户端IP地址
	 *
	 * @param boolean $s_type ip类型[ip|long]
	 * @return string $ip
	 */
	public static function get_client_ip($b_ip=true) {
		$arr_ip_header = array(
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'REMOTE_ADDR',
				'HTTP_CDN_SRC_IP',
				'HTTP_PROXY_CLIENT_IP',
				'HTTP_WL_PROXY_CLIENT_IP' 
		);
		$client_ip = 'unknown';
		foreach ($arr_ip_header as $key) {
			if (!empty($_SERVER[$key]) && strtolower($_SERVER[$key]) != 'unknown') {
				$client_ip = $_SERVER[$key];
				break;
			}
		}
		if ($pos = strpos($client_ip, ',')) {
			$client_ip = substr($client_ip, $pos + 1);
		}
		return $client_ip;
	}

	/**
	 * 获取请求IP
	 */
	public static function ip() {
		if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
			$ip = getenv('HTTP_CLIENT_IP');
		} elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
			$ip = getenv('REMOTE_ADDR');
		} elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches[0] : '';
	}

	/**
	 * 获取HTTP回应内容
	 */
	public static function curl_get_contents($url, $user_agent=false) {
		$ch = curl_init();
		$timeout = 30;
		$user_agent = $user_agent ?: 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; WOW64; Trident/4.0; SLCC1)';
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$file_contents = curl_exec($ch);
		curl_close($ch);
		return $file_contents;
	}

	public static function remote_get($url, $headers=false) {
		$timeout = 30;
		$_headers = '';
		if (is_array($headers)) {
			foreach ($headers as $name => $value) {
				$header = sprintf('%s: %s', $name, $value);
				$_headers .= $header . "\r\n";
			}
		}
		$opts = array(
			'http' => array(
				'method' => 'GET',
				'header' => "Connection: close\r\n" . $_headers,
				'user_agent' => $_SERVER['HTTP_USER_AGENT'],
				'referer' => $_SERVER['HTTP_REFERER'],
				'timeout' => intval($timeout)
			)
		);
		
		$context = stream_context_create($opts);
		return file_get_contents($url, false, $context);
	}

	public static function remote_download($url, $file_name=false, $before=null) {
		$fp = @fopen($url, 'r');
		if (! $fp) return false;
		//$res = fseek($fp, 0, SEEK_END);
		//if ($res === 0) $fsize = ftell($fp);
		$fstat = fstat($fp);
		$fsize = $fstat['size'];
		//print_r(stream_get_meta_data($fp));
		set_time_limit(0);
		header('Cache-Control: public');
		header('Pragma: public');
		header('Content-Type: application/octet-stream');
		header('Accept-Ranges: bytes');
		header('Content-Length: '. $fsize);
		$headers = $http_response_header; //get_headers($url);
		foreach ($headers as $header) header($header);
		if ($file_name) header('Content-Disposition: attachment; filename="'.$file_name.'"');
		if (is_callable($before)) call_user_func($before, $fp);
		if (isset($_SERVER['HTTP_RANGE']) && (!empty($_SERVER['HTTP_RANGE'])) && 
			preg_match('/^bytes=([0-9]+)-$/i', $_SERVER['HTTP_RANGE'], $match) && (intval($match[1]) < $fsize)) {
			$start = intval($match[1]);
		} else {
			$start = 0;
		}
		if ($start > 0) {
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: bytes '. $start .'-'.($fsize - 1). '/' .$fsize);
			header('Content-Length: '.($fsize - $start));
		}
		fseek($fp, $start);
		while (!feof($fp)) {
			print fread($fp, 10240); flush();
		}
		fclose ($fp);
		
		return true;
	}

	/**
	 * 使用 curl 发起 http 请求
	 *
	 * @param string $url
	 * @param mixed $params
	 * @param string $method (GET | POST | DELETE | PUT)
	 * @param array $curlOptions
	 * @return Array [(string)response, (array)headers]
	 */
	public static function curl_request($url, $params = [], $method = 'GET', Array $curlOptions = []) {
		$ch = curl_init();
		$strParams = (is_array($params)) ? http_build_query($params) : $params;
		
		switch (strtoupper($method)) {
			default:
			case 'GET':
				$url .= (strpos($url,'?') === FALSE ? '?' : '&').$strParams;
			break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
			break;
			case 'PUT':
			case 'DELETE':
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
				curl_setopt($ch, CURLOPT_POSTFIELDS, $strParams);
			break;
		}
		
		if (preg_match('!^https://!',$url)) {
			curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_ENCODING, 1);
		
		if (count($curlOptions)) {
			curl_setopt_array($ch, $curlOptions);
		}
		
		$response = curl_exec($ch);
		$headers = curl_getinfo($ch);
		$data = [
			'response' => $response,
			'headers' => $headers
		];
		$error = curl_error($ch);
		$errorNo = curl_errno($ch);
		curl_close($ch);
		if ($data['response'] === FALSE) {
			throw new \Exception($error, $errorNo);
		} else {
			return $data;
		}
	}

	public static function http_copy($url, $file='', $timeout=60) {
		$file = empty($file) ? pathinfo($url,PATHINFO_BASENAME) : $file;
		$dir = pathinfo($file,PATHINFO_DIRNAME);
		!is_dir($dir) && @mkdir($dir,0755,true);
		$url = str_replace(' ','%20',$url);
		
		if(function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$temp = curl_exec($ch);
			$sta = curl_getinfo($ch);
			if(intval($sta['http_code']) != 200) {
				return false;
			}
			if(@file_put_contents($file, $temp) && !curl_error($ch)) {
				return $file;
			} else {
				//echo 'error:'.curl_error($ch);
				return false;
			}
		} else {
			$opts = array(
				'http'=>array(
						'method' => 'GET',
						'header' => "Connection: close\r\n",
						'timeout' => $timeout
					)
			);
			$context = stream_context_create($opts);
			if(@copy($url, $file, $context)) {
				//$http_response_header
				return $file;
			} else {
				return false;
			}
		}
	}
	
	public static function http_get($url) {
		$user_agent = 'HTTPGET/1.0 (compatible)';
		if ($ch = curl_init()) {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			if (stripos($url,'https://') !== false) {
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			}
			$content = curl_exec($ch);
			curl_close($ch);
			return $content;
		}
		@ini_set('user_agent', $user_agent);
		return file_get_contents($url, false
			, stream_context_create(array(
				'http' => array(
					'method' => 'GET',
					'header' => "Connection: close\r\n",
					'timeout' => 20
				)
			))
		);
	}

	/**
	 * 获取系统信息
	 */
	public static function get_sysinfo() {
		$sys_info['os'] = PHP_OS;
		$sys_info['zlib'] = function_exists('gzclose'); // zlib
		$sys_info['safe_mode'] = (boolean) ini_get('safe_mode'); // safe_mode = Off
		$sys_info['safe_mode_gid'] = (boolean) ini_get('safe_mode_gid'); // safe_mode_gid = Off
		$sys_info['timezone'] = function_exists('date_default_timezone_get') ? date_default_timezone_get() : L('no_setting');
		$sys_info['socket'] = function_exists('fsockopen');
		$sys_info['web_server'] = strpos($_SERVER['SERVER_SOFTWARE'], 'PHP') === false 
									? $_SERVER['SERVER_SOFTWARE'] . ' PHP/' . phpversion() : $_SERVER['SERVER_SOFTWARE'];
		$sys_info['phpversion'] = phpversion();
		$sys_info['fileupload'] = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : 'unknown';
		return $sys_info;
	}

	/**
	 * Force Download
	 *
	 * Generates headers that force a download to happen
	 *
	 * @access public
	 * @param string filename
	 * @param mixed the data to be downloaded
	 * @return void
	 */
	public static function force_download($filename = '', $data = '') {
		if ($filename == '' or $data == '') {
			return FALSE;
		}
		
		// Try to determine if the filename includes a file extension.
		// We need it in order to set the MIME type
		if (FALSE === strpos($filename, '.')) {
			return FALSE;
		}
		
		// Grab the file extension
		$x = explode('.', $filename);
		$extension = end($x);
		
		// Load the mime types
		if (defined('ENVIRONMENT') and is_file(APPPATH . 'config/' . ENVIRONMENT . '/mimes.php')) {
			include (APPPATH . 'config/' . ENVIRONMENT . '/mimes.php');
		} elseif (is_file(APPPATH . 'config/mimes.php')) {
			include (APPPATH . 'config/mimes.php');
		}
		
		// Set a default mime if we can't find it
		if (!isset($mimes[$extension])) {
			$mime = 'application/octet-stream';
		} else {
			$mime = (is_array($mimes[$extension])) ? $mimes[$extension][0] : $mimes[$extension];
		}
		
		// Generate the server headers
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== FALSE) {
			header('Content-Type: "' . $mime . '"');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Content-Transfer-Encoding: binary');
			header('Pragma: public');
			header('Content-Length: ' . strlen($data));
		} else {
			header('Content-Type: "' . $mime . '"');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Pragma: no-cache');
			header('Content-Length: ' . strlen($data));
		}
		
		exit($data);
	}


	public static function generate_download_header(&$name, $dataSize, $is_file=true, $gzip=false) {
		header('Content-Type: application/force-download; name="'.$name.'"');
		header('Content-Transfer-Encoding: binary');
		if ($gzip) {
			header('Content-Encoding: gzip');
		}
		header('Content-Length: '.$dataSize);
		if ($is_file && ($dataSize != 0)) {
			header("Content-Range: bytes 0-" . ($dataSize- 1) . "/" . $dataSize . ";");
		}
		header("Content-Disposition: attachment; filename=\"".$name."\"");
		header("Expires: 0");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
			header("Cache-Control: max_age=0");
			header("Pragma: public");
		}
		
		if (preg_match('/ MSIE /',$_SERVER['HTTP_USER_AGENT'])) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false);
		}

	}


	public static function get_os_from_ua($useragent = null) {
		$oslist = array(
			'Windows 10' => 'windows nt 10.0',
			'Windows 8.1' => 'windows nt 6.3',
			'Windows 8' => 'windows nt 6.2',
			'Windows 7' => 'windows nt 6.1',
			'Windows Vista' => 'windows nt 6.0',
			'Windows Server 2003' => 'windows nt 5.2',
			'Windows XP' => 'windows nt 5.1',
			'Windows 2000 sp1' => 'windows nt 5.01',
			'Windows 2000' => 'windows nt 5.0',
			'Windows NT 4.0' => 'windows nt 4.0',
			'Windows Me' => 'win 9x 4.9',
			'Windows 98' => 'windows 98',
			'Windows 95' => 'windows 95',
			'Windows Phone' => 'windows phone',
			'Windows CE' => 'windows ce',
			'Windows' => 'windows',
			'Android' => 'android',
			'BlackBerry' => 'blackberry',
			'OpenBSD' => 'openbsd',
			'SunOS' => 'sunos',
			'Ubuntu' => 'ubuntu',
			'Linux' => '(linux)|(x11)',
			'Macintosh Beta (Kodiak)' => 'mac os x beta',
			'Macintosh Cheetah' => 'mac os x 10.0',
			'Macintosh Puma' => 'mac os x 10.1',
			'Macintosh Jaguar' => 'mac os x 10.2',
			'Macintosh Panther' => 'mac os x 10.3',
			'Macintosh Tiger' => 'mac os x 10.4',
			'Macintosh Leopard' => 'mac os x 10.5',
			'Macintosh Snow Leopard' => 'mac os x 10.6',
			'Macintosh Lion' => 'mac os x 10.7',
			'Macintosh Mountain Lion' => 'mac os x 10.8',
			'Macintosh Mavericks' => 'mac os x 10.9',
			'Macintosh Yosemite' => 'mac os x 10.10',
			'Macintosh' => '(mac_powerpc)|(macintosh)',
			'QNX' => 'QNX',
			'BeOS' => 'beos',
			'iPad' => 'iPad',
			'iPod' => 'iPod',
			'iPhone' => 'iPhone',
			'OS2' => 'os\/2',
			'Nintendo' => 'nintendo',
			'Playstation' => 'playstation',
			'Xbox' => 'xbox',
			'Chrome OS' => 'cros',
			'SearchBot'=>'(nuhk)|(googlebot)|(spiderman)|(bingbot)|(baiduspider)|(\w+bot)|(slurp)|(ask jeeves\/teoma)|(ia_archiver)'
		);
		
		if ($useragent == null) {
			$useragent = $_SERVER['HTTP_USER_AGENT'];
			$useragent = strtolower($useragent);
		}
		
		$found = false;
		foreach($oslist as $os=>$match) {
			if (preg_match('/' . $match . '/i', $useragent)) {
				$found = $os;
				break;
			}
		}
		
		return $found;
	}

	public static function is_mobile() {
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|'
			.'iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|'
			.'phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|'
			.'xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|'
			.'ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|'
			.'bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|'
			.'da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|'
			.'ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|'
			.'hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|'
			.'idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|'
			.'le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|'
			.'me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|'
			.'n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|'
			.'pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|'
			.'qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|'
			.'sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|'
			.'so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|'
			.'to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|'
			.'vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i'
			, substr($useragent, 0, 4)))
			return true;
		else
			return false;
	}

	/**
	 * Parses a user agent string into its important parts
	 *
	 * @author Jesse G. Donat <donatj@gmail.com>
	 * @link https://github.com/donatj/PhpUserAgent
	 * @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
	 * @param string|null $u_agent User agent string to parse or null. Uses $_SERVER['HTTP_USER_AGENT'] on NULL
	 * @throws InvalidArgumentException on not having a proper user agent to parse.
	 * @return string[] an array with browser, version and platform keys
	 */
	public static function parse_user_agent($u_agent = null) {
		if( is_null($u_agent) ) {
			if( isset($_SERVER['HTTP_USER_AGENT']) ) {
				$u_agent = $_SERVER['HTTP_USER_AGENT'];
			} else {
				throw new \InvalidArgumentException('parse_user_agent requires a user agent');
			}
		}
		$platform = null;
		$browser  = null;
		$version  = null;
		$empty = array( 'platform' => $platform, 'browser' => $browser, 'version' => $version );
		if( !$u_agent ) return $empty;
		
		if( preg_match('/\((.*?)\)/im', $u_agent, $parent_matches) ) {
			preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|iPod|Linux|Macintosh|Windows(\ Phone)?|'
					.'Silk|linux-gnu|BlackBerry|PlayBook|(New\ )?|Nintendo\ (WiiU?|3?DS)|Xbox(\ One)?)'
					.'(?:\ [^;]*)?(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);
			$priority           = array( 'Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android' );
			$result['platform'] = array_unique($result['platform']);
			if( count($result['platform']) > 1 ) {
				if( $keys = array_intersect($priority, $result['platform']) ) {
					$platform = reset($keys);
				} else {
					$platform = $result['platform'][0];
				}
			} elseif( isset($result['platform'][0]) ) {
				$platform = $result['platform'][0];
			}
		}
		if( $platform == 'linux-gnu' ) {
			$platform = 'Linux';
		} elseif( $platform == 'CrOS' ) {
			$platform = 'Chrome OS';
		}
		
		preg_match_all('%(?P<browser>Camino|Kindle(\ Fire)?|Firefox|Iceweasel|Safari|MSIE|Trident|AppleWebKit|TizenBrowser|Chrome|
				Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|CriOS|
				Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
			$u_agent, $result, PREG_PATTERN_ORDER);
		
		// If nothing matched, return null (to avoid undefined index errors)
		if( !isset($result['browser'][0]) || !isset($result['version'][0]) ) {
			if( preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result) ) {
				return array( 'platform' => $platform ?: null, 'browser' => $result['browser'], 
								'version' => isset($result['version']) ? $result['version'] ?: null : null );
			}
			return $empty;
		}
		if( preg_match('/rv:(?P<version>[0-9A-Z.]+)/si', $u_agent, $rv_result) ) {
			$rv_result = $rv_result['version'];
		}
		
		$browser = $result['browser'][0];
		$version = $result['version'][0];
		$lowerBrowser = array_map('strtolower', $result['browser']);
		$find = function ( $search, &$key ) use ( $lowerBrowser ) {
			$xkey = array_search(strtolower($search), $lowerBrowser);
			if( $xkey !== false ) {
				$key = $xkey;
				return true;
			}
			return false;
		};
		
		$key  = 0;
		$ekey = 0;
		if( $browser == 'Iceweasel' ) {
			$browser = 'Firefox';
		} elseif( $find('Playstation Vita', $key) ) {
			$platform = 'PlayStation Vita';
			$browser  = 'Browser';
		} elseif( $find('Kindle Fire', $key) || $find('Silk', $key) ) {
			$browser  = $result['browser'][$key] == 'Silk' ? 'Silk' : 'Kindle';
			$platform = 'Kindle Fire';
			if( !($version = $result['version'][$key]) || !is_numeric($version[0]) ) {
				$version = $result['version'][array_search('Version', $result['browser'])];
			}
		} elseif( $find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS' ) {
			$browser = 'NintendoBrowser';
			$version = $result['version'][$key];
		} elseif( $find('Kindle', $key) ) {
			$browser  = $result['browser'][$key];
			$platform = 'Kindle';
			$version  = $result['version'][$key];
		} elseif( $find('OPR', $key) ) {
			$browser = 'Opera Next';
			$version = $result['version'][$key];
		} elseif( $find('Opera', $key) ) {
			$browser = 'Opera';
			$find('Version', $key);
			$version = $result['version'][$key];
		} elseif( $find('Midori', $key) ) {
			$browser = 'Midori';
			$version = $result['version'][$key];
		} elseif( $browser == 'MSIE' || ($rv_result && $find('Trident', $key)) || $find('Edge', $ekey) ) {
			$browser = 'MSIE';
			if( $find('IEMobile', $key) ) {
				$browser = 'IEMobile';
				$version = $result['version'][$key];
			} elseif( $ekey ) {
				$version = $result['version'][$ekey];
			} else {
				$version = $rv_result ?: $result['version'][$key];
			}
			if( version_compare($version, '12', '>=') ) {
				$browser = 'Edge';
			}
		} elseif( $find('Vivaldi', $key) ) {
			$browser = 'Vivaldi';
			$version = $result['version'][$key];
		} elseif( $find('Chrome', $key) || $find('CriOS', $key) ) {
			$browser = 'Chrome';
			$version = $result['version'][$key];
		} elseif( $browser == 'AppleWebKit' ) {
			if( ($platform == 'Android' && !($key = 0)) ) {
				$browser = 'Android Browser';
			} elseif( strpos($platform, 'BB') === 0 ) {
				$browser  = 'BlackBerry Browser';
				$platform = 'BlackBerry';
			} elseif( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
				$browser = 'BlackBerry Browser';
			} elseif( $find('Safari', $key) ) {
				$browser = 'Safari';
			} elseif( $find('TizenBrowser', $key) ) {
				$browser = 'TizenBrowser';
			}
			$find('Version', $key);
			$version = $result['version'][$key];
		} elseif( $key = preg_grep('/playstation \d/i', array_map('strtolower', $result['browser'])) ) {
			$key = reset($key);
			$platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $key);
			$browser  = 'NetFront';
		}
		
		return array( 'platform' => $platform ?: null, 'browser' => $browser ?: null, 'version' => $version ?: null );
	}

}