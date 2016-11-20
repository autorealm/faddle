<?php namespace Faddle\Common\Util;

/**
 * 常用方法类
 */
class Common {

	/* TEXT */
	public static function str_split($string, $length = 1) {
		$string = preg_split('~~u', $string, null, PREG_SPLIT_NO_EMPTY);
		if ($length > 1) {
			$string = array_map('implode', array_chunk($string, $length));
		}
		return $string;
	}
	public static function substr($string, $offset = null, $length = null) {
		return implode('', array_slice(self::str_split($string), $offset, $length));
	}
	public static function strlen($string) {
		return count(self::str_split($string));
	}
	public static function ord($string) {
		$pack = @unpack('N', iconv('UTF-8', 'UCS-4BE', $string));
		if (is_array($pack)) return $pack[1];
		return false;
	}
	public static function daterang($start, $end, $format='Y-m-d', $pick=false, $next='+1 day') {
		$rang = array();
		if ($start == 'week') { //本周
			$date = new \DateTime();
			$date->modify('this week');
			$start = $date->format('Y-m-d');
			$date->modify('this week +6 days');
			$end = $date->format('Y-m-d');
		} else if ($start == 'month') { //本月
			$start = date('Y-m-01');
			$end = date('Y-m-d',strtotime('+1 month -1 day', strtotime($start)));
		} else if ($start == 'year') { //今年
			$start = date('Y-01-01');
			$end = date('Y-m-d',strtotime('+1 year -1 day', strtotime($start)));
		}
		
		$dt_start = strtotime($start);
		$dt_end = strtotime($end);
		
		while ($dt_start <= $dt_end) {
			$rang[] = date($format, $pick ? strtotime($pick, $dt_start) : $dt_start);
			$dt_start = strtotime($next,$dt_start);
		}
		return $rang;
	}
	
	/**
	 * To calculate distance between to coordinaces
	 * @param  <type> $lat1
	 * @param  <type> $lon1
	 * @param  <type> $lat2
	 * @param  <type> $lon2
	 * @param  <type> $unit (M = miles,K=kilometers,N=nautical)
	 * @return <type>
	 */
	public static function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit='M') {
		$theta = $lon1 - $lon2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);
		if ($unit == 'K')
			return ($miles * 1.609344);
		else if ($unit == 'N')
			return ($miles * 0.8684);
		else
			return $miles;
	}

	function Ellipsize($str, $max_length, $position = 1, $ellipsis = '...') {
		if (self::strlen($str) <= $max_length) {
			return $str;
		}
		$beg = self::substr($str, 0, floor($max_length * $position));
		$position = ($position > 1) ? 1 : $position;
		if ($position === 1) {
			$end = self::substr($str, 0, -($max_length - strlen($beg)));
		} else {
			$end = self::substr($str, -($max_length - strlen($beg)));
		}
		
		return $beg.$ellipsis.$end;
	}

	public static function Truncate($string, $limit, $more = '...') {
		if (self::strlen($string = trim($string)) > $limit) {
			return preg_replace('~^(.{1,' . $limit . '}(?<=\S)(?=\s)|.{' . $limit . '}).*$~su', '$1', $string) . $more;
		}
		return $string;
	}

	public static function Reduce($string, $search, $modifiers = false) {
		return preg_replace('~' . preg_quote($search, '~') . '+~' . $modifiers, $search, $string);
	}

	public static function Unaccent($string) {
		if (extension_loaded('intl') === true) {
			$string = Normalizer::normalize($string, Normalizer::FORM_KD);
		}
		if (strpos($string = htmlentities($string, ENT_QUOTES, 'UTF-8'), '&') !== false) {
			$string = html_entity_decode(
				preg_replace('~&([a-z]{1,2})(?:acute|caron|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i' , '$1', $string)
				, ENT_QUOTES, 'UTF-8');
		}
		return $string;
	}

	/* ARRAY */
	public static function Sort($array, $natural = true, $reverse = false) {
		if (! is_array($array)) return false;
		if (extension_loaded('intl') === true) {
			if (is_object($collator = collator_create('root')) === true) {
				if ($natural === true) {
					$collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
				}
				$collator->asort($array);
			}
		} else if (function_exists('array_multisort') === true) {
			$data = array();
			foreach ($array as $key => $value) {
				if ($natural === true) {
					$value = preg_replace('~([0-9]+)~e', 'sprintf("%032d", "$1")', $value);
				}
				if (strpos($value = htmlentities($value, ENT_QUOTES, 'UTF-8'), '&') !== false) {
					$value = html_entity_decode(
						preg_replace('~&([a-z]{1,2})(acute|caron|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i' 
						, '$1' . chr(255) . '$2', $value) , ENT_QUOTES, 'UTF-8');
				}
				$data[$key] = strtolower($value);
			}
			array_multisort($data, $array);
		}
		return ($reverse === true) ? array_reverse($array, true) : $array;
	}

	public static function Export($name, $data) {
		$result = null;
		if (is_scalar($data) === true) {
			$result .= sprintf("%s = %s;\n", $name, var_export($data, true));
		} else if (is_array($data) === true) {
			$result .= sprintf("%s = array();\n", $name);
			foreach ($data as $key => $value) {
				$result .= self::Export($name . '[' . var_export($key, true) . ']', $value) . "\n";
			}
			if (array_keys($data) === range(0, count($data))) {
				$result = preg_replace('~^' . sprintf(preg_quote($name . '[%s]', '~'), '\d+') . '~m', $name . '[]', $result);
			}
		} else if (is_object($data) === true) {
			$result .= sprintf("%s = %s;\n", $name, preg_replace('~\n\s*~', '', var_export($data, true)));
		} else {
			$result .= sprintf("%s = %s;\n", $name, 'null');
		}
		return rtrim($result, "\n");
	}

	public static function Filter($data, $control = true, $encoding = null) {
		if (is_array($data) === true) {
			$result = array();
			foreach ($data as $key => $value) {
				$result[self::Filter($key, $control, $encoding)] = self::Filter($value, $control, $encoding);
			}
			return $result;
		} else if (is_string($data) === true) {
			if (preg_match('~[^\x00-\x7F]~', $data) > 0) {
				if ((empty($encoding) === true) && (function_exists('mb_detect_encoding') === true)) {
					$encoding = mb_detect_encoding($data, 'ASCII,ISO-8859-15,UTF-8', true);
				}
				$data = @iconv((empty($encoding) === true) ? 'UTF-8' : $encoding, 'UTF-8//IGNORE', $data);
			}
			return ($control === true) ? preg_replace('~\p{C}+~u', '', $data) 
				: preg_replace(array('~\R~u', '~[^\P{C}\t\n]+~u'), array("\n", ''), $data);
		}

		return $data;
	}

	public static function Dump() {
		foreach (func_get_args() as $argument) {
			if (is_resource($argument) === true) {
				$result = sprintf('%s (#%u)', get_resource_type($argument), $argument);
			} else if ((is_array($argument) === true) || (is_object($argument) === true)) {
				$result = rtrim(print_r($argument, true));
			} else {
				$result = stripslashes(preg_replace("~^'|'$~", '', var_export($argument, true)));
			}
			if (strcmp('cli', PHP_SAPI) !== 0) {
				if (strpbrk($result, '<>') !== false) {
					$result = str_replace(array('<', '>'), array('&lt;', '&gt;'), $result);
				}
				$result = '<pre style="background: #df0; margin: 5px; padding: 5px; text-align: left;">' . $result . '</pre>';
			}
			echo $result . "\n";
		}
	}

	/* DATE */
	public static function Date($format = 'U', $date = 'now', $zone = null) {
		if (is_object($result = date_create($date)) === true) {
			if (isset($zone) === true) {
				if (is_string($zone) !== true) {
					$zone = date_default_timezone_get();
				}
				@date_timezone_set($result, timezone_open($zone));
			}
			if (count($arguments = array_slice(func_get_args(), 3)) > 0) {
				foreach (array_filter($arguments, 'strtotime') as $argument) {
					date_modify($result, $argument);
				}
			}
			return date_format($result, str_replace(explode('|', 'DATE|TIME|YEAR|ZONE'), explode('|', 'Y-m-d|H:i:s|Y|T'), $format));
		}
		return false;
	}

	public static function Cache($key, $value = null, $ttl = 60) {
		if (extension_loaded('apc') === true) {
			if ((isset($value) === true) && (apc_store($key, $value, intval($ttl)) !== true)) {
				return $value;
			}
			return apc_fetch($key);
		}
		return (isset($value) === true) ? $value : false;
	}

	public static function Pagination($data, $limit = null, $current = null, $adjacents = null) {
		$result = array();
		if (isset($data, $limit) === true) {
			$result = range(1, ceil($data / $limit));
			if (isset($current, $adjacents) === true) {
				if (($adjacents = floor($adjacents / 2) * 2 + 1) >= 1) {
					$result = array_slice($result, max(0, min(count($result) - $adjacents
						, intval($current) - ceil($adjacents / 2))), $adjacents);
				}
			}
		}
		return $result;
	}

	public static function Benchmark($callbacks, $iterations = 100, $relative = false) {
		set_time_limit(0);
		if (count($callbacks = array_filter((array) $callbacks, 'is_callable')) > 0) {
			$result = array_fill_keys($callbacks, 0);
			$arguments = array_slice(func_get_args(), 3);
			for ($i = 0; $i < $iterations; ++$i) {
				foreach ($result as $key => $value) {
					$value = microtime(true); call_user_func_array($key, $arguments); $result[$key] += microtime(true) - $value;
				}
			}
			asort($result, SORT_NUMERIC);
			foreach (array_reverse($result) as $key => $value) {
				if ($relative === true) {
					$value /= reset($result);
				}
				$result[$key] = number_format($value, 8, '.', '');
			}
			return $result;
		}
		return false;
	}

	public static function Value($data, $key = null, $default = false) {
		if (isset($key) === true) {
			if (is_array($key) !== true) {
				$key = explode('.', $key);
			}
			foreach ((array) $key as $value) {
				$data = (is_object($data) === true) ? get_object_vars($data) : $data;
				if ((is_array($data) !== true) || (array_key_exists($value, $data) !== true)) {
					return $default;
				}
				$data = $data[$value];
			}
		}
		return $data;
	}

	public static function Voodoo($data) {
		if ((version_compare(PHP_VERSION, '5.4.0', '<') === true) && (get_magic_quotes_gpc() === 1)) {
			if (is_array($data) === true) {
				$result = array();
				foreach ($data as $key => $value) {
					$result[self::Voodoo($key)] = self::Voodoo($value);
				}
				return $result;
			}
			return (is_string($data) === true) ? stripslashes($data) : $data;
		}
		return $data;
	}

	/* HTTP */
	public static function URL($url = null, $path = null, $query = null) {
		if (isset($url) === true) {
			if ((is_array($url = @parse_url($url)) === true) && (isset($url['scheme'], $url['host']) === true)) {
				$result = strtolower($url['scheme']) . '://';
				if ((isset($url['user']) === true) || (isset($url['pass']) === true)) {
					$result .= ltrim(rtrim(self::Value($url, 'user') . ':' . self::Value($url, 'pass'), ':') . '@', '@');
				}
				$result .= strtolower($url['host']) . '/';
				if ((isset($url['port']) === true) && (strcmp($url['port'], getservbyname($url['scheme'], 'tcp')) !== 0)) {
					$result = rtrim($result, '/') . ':' . intval($url['port']) . '/';
				}
				if (($path !== false) && ((isset($path) === true) || (isset($url['path']) === true))) {
					if (is_scalar($path) === true) {
						if (($query !== false) && (preg_match('~[?&]~', $path) > 0)) {
							$url['query'] = ltrim(rtrim(self::Value($url, 'query'), '&') . '&' 
								. preg_replace('~^.*?[?&]([^#]*).*$~', '$1', $path) , '&');
						}
						$url['path'] = '/' . ltrim(preg_replace('~[?&#].*$~', '', $path), '/');
					}
					while (preg_match('~/[.][.]?(?:/|$)~', $url['path']) > 0) {
						$url['path'] = preg_replace(array('~/+~', '~/[.](?:/|$)~', '~(?:^|/[^/]+)/[.]{2}(?:/|$)~'), '/', $url['path']);
					}
					$result .= preg_replace('~/+~', '/', ltrim($url['path'], '/'));
				}
				if (($query !== false) && ((isset($query) === true) || (isset($url['query']) === true))) {
					parse_str(self::Value($url, 'query'), $url['query']);
					if (is_array($query) === true) {
						$url['query'] = array_merge($url['query'], $query);
					}
					if ((count($url['query'] = self::Voodoo(array_filter($url['query'], 'count'))) > 0) 
						&& (ksort($url['query']) === true)) {
						$result .= rtrim('?' . http_build_query($url['query'], '', '&'), '?');
					}
				}
				return preg_replace('~(%[0-9a-f]{2})~e', 'strtoupper("$1")', $result);
			}
			return false;
		} else if (strlen($scheme = preg_replace('~^www$~i', 'http', getservbyport(
			self::Value($_SERVER, 'SERVER_PORT', 80), 'tcp'))) > 0) {
			return self::URL($scheme . '://' . self::Value($_SERVER, 'HTTP_HOST') 
				. self::Value($_SERVER, 'REQUEST_URI'), $path, $query);
		}
		return false;
	}

	/* HTML */
	public static function DOM($html, $xpath = null, $key = null, $default = false) {
		if (count(array_filter(array('dom', 'SimpleXML'), 'extension_loaded')) == 2) {
			if (is_object($html) === true) {
				if (isset($xpath) === true) {
					$html = $html->xpath($xpath);
				}
				return (isset($key) === true) ? parent::Value($html, $key, $default) : $html;
			} else if (is_string($html) === true) {
				$dom = new DOMDocument();
				if (libxml_use_internal_errors(true) === true) {
					libxml_clear_errors();
				}
				if ($dom->loadHTML(html_entities($html)) === true) {
					return self::DOM(simplexml_import_dom($dom), $xpath, $key, $default);
				}
			}
		}
		return false;
	}

	/* NET */
	public static function CURL($url, $data = null, $method = 'GET', $cookie = null, $options = null, $retries = 3) {
		$result = false;
		if ((extension_loaded('curl') === true) && (is_resource($curl = curl_init()) === true)) {
			if (($url = static::URL($url, null, (preg_match('~^(?:POST|PUT)$~i', $method) > 0) ? null : $data)) !== false) {
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_FAILONERROR, true);
				curl_setopt($curl, CURLOPT_AUTOREFERER, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				if (preg_match('~^(?:DELETE|GET|HEAD|OPTIONS|POST|PUT)$~i', $method) > 0) {
					if (preg_match('~^(?:HEAD|OPTIONS)$~i', $method) > 0) {
						curl_setopt_array($curl, array(CURLOPT_HEADER => true, CURLOPT_NOBODY => true));
					} else if (preg_match('~^(?:POST|PUT)$~i', $method) > 0) {
						if (is_array($data) === true) {
							foreach (preg_grep('~^@~', $data) as $key => $value) {
								$data[$key] = sprintf('@%s', ph()->Disk->Path(ltrim($value, '@')));
							}
							if (count($data) != count($data, COUNT_RECURSIVE)) {
								$data = http_build_query($data, '', '&');
							}
						}
						curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
					}
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
					if (isset($cookie) === true) {
						curl_setopt_array($curl, array_fill_keys(array(CURLOPT_COOKIEJAR, CURLOPT_COOKIEFILE), strval($cookie)));
					}
					if ((intval(ini_get('safe_mode')) == 0) && (ini_set('open_basedir', null) !== false)) {
						curl_setopt_array($curl, array(CURLOPT_MAXREDIRS => 5, CURLOPT_FOLLOWLOCATION => true));
					}
					if (is_array($options) === true) {
						curl_setopt_array($curl, $options);
					}
					for ($i = 1; $i <= $retries; ++$i) {
						$result = curl_exec($curl);
						if (($i == $retries) || ($result !== false)) {
							break;
						}
						usleep(pow(2, $i - 2) * 1000000);
					}
				}
			}
			curl_close($curl);
		}
		return $result;
	}

}
