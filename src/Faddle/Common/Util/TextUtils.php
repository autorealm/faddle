<?php namespace Faddle\Commmon\Util;


class TextUtils {

	public static $HC = array('∵', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…');
	public static $EC = array(' ', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;');

	/**
	 * 截取字符串（支持中文）
	 */
	public static function cutstr($string, $begin, $length) {
		if (strlen($string) < $length) {
			return substr($string, $begin);
		}
		$char = ord($string[$begin + $length - 1]);
		if ($char >= 224 && $char <= 239) {
			$str = substr($string, $begin, $length - 1);
			return $str;
		}
		$char = ord($string[$begin + $length - 2]);
		if ($char >= 224 && $char <= 239) {
			$str = substr($string, $begin, $length - 2);
			return $str;
		}
		return substr($string, $begin, $length);
	}

	/**
	 * 获取指定长度的 utf8 字符串
	 *
	 * @param string $string
	 * @param int $length
	 * @param string $dot
	 * @return string
	 */
	public static function usubstr($string, $length, $dot = '...') {
		if (strlen($string) <= $length)
			return $string;
		
		$strcut = '';
		$n = $tn = $noc = 0;
		
		while ($n < strlen($string)) {
			$t = ord($string[$n]);
			if ($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
				$tn = 1;
				$n++;
				$noc++;
			} elseif (194 <= $t && $t <= 223) {
				$tn = 2;
				$n += 2;
				$noc += 2;
			} elseif (224 <= $t && $t <= 239) {
				$tn = 3;
				$n += 3;
				$noc += 2;
			} elseif (240 <= $t && $t <= 247) {
				$tn = 4;
				$n += 4;
				$noc += 2;
			} elseif (248 <= $t && $t <= 251) {
				$tn = 5;
				$n += 5;
				$noc += 2;
			} elseif ($t == 252 || $t == 253) {
				$tn = 6;
				$n += 6;
				$noc += 2;
			} else {
				$n++;
			}
			if ($noc >= $length)
				break;
		}
		if ($noc > $length) {
			$n -= $tn;
		}
		if ($n < strlen($string)) {
			$strcut = substr($string, 0, $n);
			return $strcut . $dot;
		} else {
			return $string;
		}
	}

	/**
	 * 字符串截取，支持中文和其他编码
	 *
	 * @param string $str 需要转换的字符串
	 * @param string $start 开始位置
	 * @param string $length 截取长度
	 * @param string $charset 编码格式
	 * @param string $suffix 截断显示字符
	 * @return string
	 */
	public static function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
		if (function_exists("mb_substr")) {
			$i_str_len = mb_strlen($str);
			$s_sub_str = mb_substr($str, $start, $length, $charset);
			if ($length >= $i_str_len) {
				return $s_sub_str;
			}
			return $s_sub_str . '...';
		} elseif (function_exists('iconv_substr')) {
			return iconv_substr($str, $start, $length, $charset);
		}
		$re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
		$re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
		$re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
		$re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
		preg_match_all($re[$charset], $str, $match);
		$slice = join("", array_slice($match[0], $start, $length));
		if ($suffix)
			return $slice . "…";
		return $slice;
	}

	/**
	 * 获取 UTF-8 字符串长度
	 * @param string $str
	 * @return number
	 */
	public static function ustrlen($str) {
		$count = 0;
		for($i = 0; $i < strlen($str); $i++){
			$value = ord($str[$i]);
			if($value > 127) {
				$count++;
				if($value >= 192 && $value <= 223) $i++;
				elseif($value >= 224 && $value <= 239) $i = $i + 2;
				elseif($value >= 240 && $value <= 247) $i = $i + 3;
				
			}
			$count++;
		}
		return $count;
	}

	/**
	 * 扩展的 substr 函数，如果 mbstring 可用, 否则使用 substr 函数。
	 *
	 * @access public
	 * @param string $string 字符串
	 * @param integer $start 开始位置
	 * @param integer $length 字符数
	 * @return string
	 */
	function substr_utf($string, $start = 0, $length = null) {
		$start = (integer) $start >= 0 ? (integer) $start : 0;
		if (is_null($length)) {
			$length = strlen_utf($string) - $start;
		}

		if (function_exists('mb_substr')) {
			return mb_substr($string, $start, $length);
		} else {
			return substr($string, $start, $length);
		}
	}

	/**
	 * Return UTF safe string lenght
	 *
	 * @access public
	 * @param strign $string
	 * @return integer
	 */
	function strlen_utf($string) {
		if (function_exists('mb_strlen')) {
			return mb_strlen($string);
		} else {
			return strlen($string);
		}
	}

	/**
	 * 取$from~$to范围内的随机数
	 *
	 * @param $from 下限
	 * @param $to 上限
	 * @return unknown_type
	 */
	public static function randnum($from, $to) {
		$size = $from - $to; // 数值区间
		$max = 30000; // 最大
		if ($size < $max) {
			return $from + mt_rand(0, $size);
		} else {
			if ($size % $max) {
				return $from + self::randnum(0, $size / $max) * $max + mt_rand(0, $size % $max);
			} else {
				return $from + self::randnum(0, $size / $max) * $max + mt_rand(0, $max);
			}
		}
	}

	/**
	 * 产生随机字串，可用来自动生成密码 默认长度6位 字母和数字混合
	 *
	 * @param string $len 长度
	 * @param string $type 字串类型：0 字母 1 数字 2 大写字母 3 小写字母 4 中文
	 *        其他为数字字母混合(去掉了 容易混淆的字符oOLl和数字01，)
	 * @param string $addChars 额外字符
	 * @return string
	 */
	public static function randstr($len = 4, $type = 'check_code') {
		$str = '';
		switch ($type) {
			case 0 : // 大小写中英文
				$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
			break;
			case 1 : // 数字
				$chars = str_repeat('0123456789', 3);
			break;
			case 2 : // 大写字母
				$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
			case 3 : // 小写字母
				$chars = 'abcdefghijklmnopqrstuvwxyz';
			break;
			default :
				// 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
				$chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
			break;
		}
		if ($len > 10) { // 位数过长重复字符串一定次数
			$chars = $type == 1 ? str_repeat($chars, $len) : str_repeat($chars, 5);
		}
		if ($type != 4) {
			$chars = str_shuffle($chars);
			$str = substr($chars, 0, $len);
		} else {
			// 中文随机字
			for ($i = 0; $i < $len; $i++) {
				$str .= msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1);
			}
		}
		return $str;
	}

	/**
	 * 生成自动密码
	 */
	public static function makepwd() {
		$temp = '0123456789abcdefghijklmnopqrstuvwxyz' . 'ABCDEFGHIJKMNPQRSTUVWXYZ~!@#$^*)_+}{}[]|":;,.' . time();
		for ($i = 0; $i < 10; $i++) {
			$temp = str_shuffle($temp . substr($temp, -5));
		}
		return md5($temp);
	}

	/**
	 * 产生随机字符串
	 *
	 * @param string $chars 可选的 ，默认为 0123456789
	 * @param int $length 输出长度 ，默认为12
	 * @return string 字符串
	 *        
	 */
	public static function random($chars='0123456789', $length=12) {
		$hash = '';
		$max = strlen($chars) - 1;
		for ($i = 0; $i < $length; $i++) {
			$hash .= $chars[mt_rand(0, $max)];
		}
		return $hash;
	}

	/**
	 * Create a Random String
	 *
	 * Useful for generating passwords or hashes.
	 *
	 * @access public
	 * @param string type of random string. basic, alpha, alunum, numeric, nozero, unique, md5, encrypt and sha1
	 * @param integer number of characters
	 * @return string
	 */
	public static function random_string($type = 'alnum', $len = 8) {
		switch ($type) {
			case 'basic' :
				return mt_rand();
			case 'alpha' :
				$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
			case 'alnum' :
				$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
			case 'hexdec':
				$pool = '0123456789abcdef';
			break;
			case 'numeric' :
				$pool = '0123456789';
			break;
			case 'nozero' :
				$pool = '123456789';
			break;
			case 'distinct':
				$pool = '2345679ACDEFHJKLMNPRSTUVWXYZ';
			break;
			case 'unique' :
				$count = 0;
				$return = array();
				while ($count < $len) {
					$return[] = mt_rand(-128, 128);
					$return = array_flip(array_flip($return));
					$count = count($return);
				}
				shuffle($return);
				return $return;
			break;
			case 'md5' :
				return md5(uniqid(mt_rand()));
			default:
				$pool = (string) $type;
		}
		
		$str = '';
		for ($i = 0; $i < $len; $i++) {
			$str .= substr($pool, mt_rand(0, strlen($pool) - 1), 1);
		}
		return $str;
	}

	public static function url_encode($str, $charset='gbk') {
		if ($charset == 'gbk') {
			$result = RawUrlEncode($str);
		} else {
			$key = mb_convert_encoding($str, 'utf-8', 'gbk');
			$result = urlencode($key);
		}
		return $result;
	}

	/**
	 * 转换文本中的换行符为HTML格式
	 */
	public static function nl2p($string, $line_breaks = true, $xml = true) {
		$string = str_replace(array('<p>', '</p>', '<br>', '<br />'), '', $string);
	
		if ($line_breaks == true)
			return '<p>'.preg_replace(array("/([\n]{2,})/i", "/([^>])\n([^<])/i")
				, array("</p>\n<p>", '<br'.($xml == true ? ' /' : '').'>'), trim($string)).'</p>';
		else
			return '<p>'.preg_replace("/([\n]{1,})/i", "</p>\n<p>", trim($string)).'</p>';
	}
	
	public static function auto_link_urls($text) {
		$regex = '~\\b'
				.'((?:ht|f)tps?://)?' // protocol
				.'(?:[-a-zA-Z0-9]{1,63}\.)+' // host name
				.'(?:[0-9]{1,3}|aero|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|'
						.'travel|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|'
						.'bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cx|cy|cz|de|dj|dk|dm|do|dz|'
						.'ec|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|'
						.'hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|'
						.'la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|my|mz|'
						.'na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|rs|ru|rw|'
						.'sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|'
						.'tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)' // tlds
				.'(?:/[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]*?)?' // path
				.'(?:\?[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?' // query
				.'(?:#[!$-/0-9:;=@_\':;!a-zA-Z\x7f-\xff]+?)?' // fragment
				.'(?=[?.!,;:"]?(?:\s|$))~'; // punctuation and url end
		
		$result = "";
		$position = 0;
		
		while (preg_match($regex, $text, $match, PREG_OFFSET_CAPTURE, $position)) {
			list($url, $url_pos) = $match[0];
			// Add the text before the url
			$result  .= substr($text, $position, $url_pos - $position);
			// Default to http://
			$full_url = empty($match[1][0]) ? 'http://'.$url : $url;
			// Add the hyperlink.
			$result .= html::anchor($full_url, $url);
			// New position to start parsing
			$position = $url_pos + strlen($url);
		}
	
		return $result.substr($text, $position);
	}
	
	public static function auto_link_emails($text) {
		if (preg_match_all('~\b(?<!href="mailto:|">|58;)(?!\.)[-+_a-z0-9.]++(?<!\.)@(?![-.])[-a-z0-9.]+(?<!\.)\.[a-z]{2,6}\b~i'
			, $text, $matches)) {
			foreach ($matches[0] as $match) {
				// Replace each email with an encoded mailto
				$text = str_replace($match, html::mailto($match), $text);
			}
		}
		
		return $text;
	}

	/**
	 * XSS 过滤函数
	 *
	 * @param $string
	 * @return string
	 */
	public static function remove_xss($string) {
		$string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $string);
		
		return $string;
	}

	/**
	 * Given a string, this function will determine if it potentially an
	 * XSS attack and return boolean.
	 *
	 * @param string $string
	 *  The string to run XSS detection logic on
	 * @return boolean
	 *  True if the given `$string` contains XSS, false otherwise.
	 */
	public static function detect_xss($string) {
		$contains_xss = FALSE;
		// Skip any null or non string values
		if(is_null($string) || !is_string($string)) {
			return $contains_xss;
		}
		// Keep a copy of the original string before cleaning up
		$orig = $string;
		// Set the patterns we'll test against
		$patterns = array(
			// Match any attribute starting with "on" or xmlns
			'#(<[^>]+[\x00-\x20\"\'\/])(on|xmlns)[^>]*>?#iUu',
			// Match javascript:, livescript:, vbscript: and mocha: protocols
			'!((java|live|vb)script|mocha|feed|data):(\w)*!iUu',
			'#-moz-binding[\x00-\x20]*:#u',
			// Match style attributes
			'#(<[^>]+[\x00-\x20\"\'\/])style=[^>]*>?#iUu',
			// Match unneeded tags
			'#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>?#i'
		);
		
		foreach($patterns as $pattern) {
			// Test both the original string and clean string
			if(preg_match($pattern, $string) || preg_match($pattern, $orig)){
				$contains_xss = TRUE;
			}
			if ($contains_xss === TRUE) return TRUE;
		}
		
		return FALSE;
	}

	/**
	 * 过滤ASCII码从0-28的控制字符
	 * @return String
	 */
	public static function trim_unsafe_control_chars($str) {
		$rule = '/[' . chr(1) . '-' . chr(8) . chr(11) . '-' . chr(12) . chr(14) . '-' . chr(31) . ']*/';
		return str_replace(chr(0), '', preg_replace($rule, '', $str));
	}

	/**
	 * 格式化文本域内容
	 *
	 * @param $string 文本域内容
	 * @return string
	 */
	public static function trim_textarea($string) {
		$string = nl2br(str_replace(' ', '&nbsp;', $string));
		return $string;
	}

	/**
	 * 查询字符是否存在于某字符串
	 *
	 * @param $haystack 字符串
	 * @param $needle 要查找的字符
	 * @return bool
	 */
	public static function str_exists($haystack, $needle) {
		return !(strpos($haystack, $needle) === false);
	}

	public static function add_magic_quotes($array) {
		foreach ((array) $array as $k => $v) {
			if (is_array($v)) {
				$array[$k] = self::add_magic_quotes($v);
			} else {
				$array[$k] = addslashes($v);
			}
		}
		return $array;
	}

	public static function add_slashes($string) {
		if (! get_magic_quotes_gpc()) {
			if (is_array($string)) {
				foreach ($string as $key => $val) {
					$string[$key] = self::add_slashes($val);
				}
			} else {
				$string = addslashes($string);
			}
		}
		return $string;
	}

	public function convert_to_utf8($str, $encoding) {
		if (function_exists("mb_convert_encoding")) {
			return mb_convert_encoding($str, 'UTF-8', $encoding);
		} elseif (function_exists("iconv")) {
			return @iconv($encoding, 'UTF-8', $str);
		}
	
		return FALSE;
	}

	public static function safe_encoding($string, $outEncoding ='UTF-8') {
		$encoding = "UTF-8";
		for($i=0; $i<strlen($string); $i++) {
			if(ord($string{$i})<128) continue;
			
			if((ord($string{$i})&224)==224) {
				//第一个字节判断通过
				$char = $string{++$i};
				if((ord($char)&128)==128) {
					//第二个字节判断通过
					$char = $string{++$i};
					if((ord($char)&128)==128) {
						$encoding = "UTF-8";
						break;
					}
				}
			}
			
			if((ord($string{$i})&192)==192) {
				//第一个字节判断通过
				$char = $string{++$i};
				if((ord($char)&128)==128) {
					// 第二个字节判断通过
					$encoding = "GB2312";
					break;
				}
			}
		}
		
		if(strtoupper($encoding) == strtoupper($outEncoding))
			return $string;
		else
			return iconv($encoding,$outEncoding,$string);
	}

	/**
	 * Modifies a string to remove all non ASCII characters and spaces.
	 * @param string $text
	 * @return string
	 */
	public static function slugify($text) {
		if (empty($text)) return "";
		// replace non letter or digits by -
		$text = preg_replace('~[^\\pL\d]+~u', '-', $text);
		// trim
		$text = trim($text, '-');
		// transliterate
		if (function_exists('iconv')) {
			$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		}
		// lowercase
		$text = strtolower($text);
		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);
		if (empty($text)) {
			return 'n-a';
		}
		return $text;
	}

	/**
	 * 将字符串转换为变量
	 *
	 * @param string $data
	 * @return var
	 *
	 */
	public static function str2var($data) {
		@eval("\$var = $data;");
		return $var;
	}

	/**
	 * 将变量转换为字符串
	 *
	 * @param var $data
	 * @return string
	 *
	 */
	public static function var2str($data) {
		return addslashes(var_export($data, true));
	}

	/**
	 * 转换字节数为其他单位
	 *
	 * @param string $filesize
	 * @return string
	 *
	 */
	public static function sizestr($filesize) {
		$filesize = floatval($filesize);
		if ($filesize >= 1073741824) {
			$filesize = round($filesize / 1073741824 * 100) / 100 . ' GB';
		} elseif ($filesize >= 1048576) {
			$filesize = round($filesize / 1048576 * 100) / 100 . ' MB';
		} elseif ($filesize >= 1024) {
			$filesize = round($filesize / 1024 * 100) / 100 . ' KB';
		} else {
			$filesize = $filesize . ' Bytes';
		}
		return $filesize;
	}

	/**
	 * 字符串加密、解密函数
	 *
	 * @param string $txt
	 * @param string $operation
	 * @param string $key
	 * @param string $expiry
	 * @return string
	 *
	 */
	public static function sys_auth($string, $operation = 'ENCODE', $key = '', $expiry = 0) {
		$key_length = 4;
		$key = md5($key != '' ? $key : pc_base::load_config('system', 'auth_key'));
		$fixedkey = md5($key);
		$egiskeys = md5(substr($fixedkey, 16, 16));
		$runtokey = $key_length ? ($operation == 'ENCODE' ? substr(md5(microtime(true)), -$key_length) : substr($string, 0, $key_length)) : '';
		$keys = md5(substr($runtokey, 0, 16) . substr($fixedkey, 0, 16) . substr($runtokey, 16) . substr($fixedkey, 16));
		$string = $operation == 'ENCODE' ? sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $egiskeys), 0, 16) 
			. $string : base64_decode(strtr(substr($string, $key_length), '-_', '+/'));
		
		if ($operation == 'ENCODE') {
			$string .= substr(md5(microtime(true)), -4);
		}
		if (function_exists('mcrypt_encrypt') == true) {
			$result = self::sys_auth_ex($string, $operation, $fixedkey);
		} else {
			$i = 0;
			$result = '';
			$string_length = strlen($string);
			for ($i = 0; $i < $string_length; $i++) {
				$result .= chr(ord($string{$i}) ^ ord($keys{$i % 32}));
			}
		}
		if ($operation == 'DECODE') {
			$result = substr($result, 0, -4);
		}
		
		if ($operation == 'ENCODE') {
			return $runtokey . rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
		} else {
			if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) 
				&& substr($result, 10, 16) == substr(md5(substr($result, 26) . $egiskeys), 0, 16)) {
				return substr($result, 26);
			} else {
				return '';
			}
		}
	}

	/**
	 * 字符串加密、解密扩展函数
	 *
	 * @param string $txt
	 * @param string $operation
	 * @param string $key
	 * @return string
	 *
	 */
	public static function sys_auth_ex($string, $operation = 'ENCODE', $key) {
		$encrypted_data = "";
		$td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
		
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		$key = substr($key, 0, mcrypt_enc_get_key_size($td));
		mcrypt_generic_init($td, $key, $iv);
		
		if ($operation == 'ENCODE') {
			$encrypted_data = mcrypt_generic($td, $string);
		} else {
			$encrypted_data = rtrim(mdecrypt_generic($td, $string));
		}
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		return $encrypted_data;
	}

	/**
	 * Indents a flat JSON string to make it more human-readable.
	 *
	 * @param string $json The original JSON string to process.
	 *
	 * @return string Indented version of the original JSON string.
	 */
	public static function pretty_print_json($json) {
		$result      = '';
		$pos         = 0;
		$strLen      = strlen($json);
		$indentStr   = '  ';
		$newLine     = "\n";
		$prevChar    = '';
		$outOfQuotes = true;
		
		for ($i=0; $i<=$strLen; $i++) {
			// Grab the next character in the string.
			$char = substr($json, $i, 1);
			// Are we inside a quoted string?
			if ($char == '"' && $prevChar != '\\') {
				$outOfQuotes = !$outOfQuotes;
				// If this character is the end of an element,
				// output a new line and indent the next line.
			} else if (($char == '}' || $char == ']') && $outOfQuotes) {
				$result .= $newLine;
				$pos --;
				for ($j=0; $j<$pos; $j++) {
					$result .= $indentStr;
				}
			}
			// Add the character to the result string.
			$result .= $char;
			// If the last character was the beginning of an element,
			// output a new line and indent the next line.
			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos ++;
				}
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
			$prevChar = $char;
		}
		
		return $result;
	}

	public static function is_ascii($str) {
		return (preg_match('/[^\x00-\x7F]/S', $str) === 0);
	}

	/**
	 * 判断字符串是否为utf8编码，英文和半角字符返回ture
	 * @param $string
	 * @return bool
	 */
	public static function is_utf8($string) {
		return preg_match('%^(?:
						[\x09\x0A\x0D\x20-\x7E] # ASCII
						| [\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
						| \xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
						| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
						| \xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
						| \xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
						| [\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
						| \xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
						)*$%xs', $string);
	}

	/**
	 * 判断email格式是否正确
	 * @param $email
	 */
	public static function is_email($email) {
		return strlen($email) > 6 && preg_match("/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/", $email);
	}

	/**
	 * 自闭合html修复函数
	 * 使用方法:
	 * <code>
	 * $input = '这是一段被截断的html文本<a href="#"';
	 * echo Typecho_Common::fixHtml($input);
	 * //output: 这是一段被截断的html文本
	 * </code>
	 *
	 * @access public
	 * @param string $string 需要修复处理的字符串
	 * @return string
	 */
	public static function fix_html($string) {
		//关闭自闭合标签
		$startPos = strrpos($string, "<");
		
		if (false == $startPos) {
			return $string;
		}
		
		$trimString = substr($string, $startPos);
		
		if (false === strpos($trimString, ">")) {
			$string = substr($string, 0, $startPos);
		}
		
		//非自闭合html标签列表
		preg_match_all("/<([_0-9a-zA-Z-\:]+)\s*([^>]*)>/is", $string, $startTags);
		preg_match_all("/<\/([_0-9a-zA-Z-\:]+)>/is", $string, $closeTags);
		
		if (!empty($startTags[1]) && is_array($startTags[1])) {
			krsort($startTags[1]);
			$closeTagsIsArray = is_array($closeTags[1]);
			foreach ($startTags[1] as $key => $tag) {
				$attrLength = strlen($startTags[2][$key]);
				if ($attrLength > 0 && "/" == trim($startTags[2][$key][$attrLength - 1])) {
					continue;
				}
				if (!empty($closeTags[1]) && $closeTagsIsArray) {
					if (false !== ($index = array_search($tag, $closeTags[1]))) {
						unset($closeTags[1][$index]);
						continue;
					}
				}
				$string .= "</{$tag}>";
			}
		}
		
		return preg_replace("/\<br\s*\/\>\s*\<\/p\>/is", '</p>', $string);
	}

	/**
	 * 处理XSS跨站攻击的过滤函数
	 *
	 * @author kallahar@kallahar.com
	 * @link http://kallahar.com/smallprojects/php_xss_filter_function.php
	 * @access public
	 * @param string $val 需要处理的字符串
	 * @return string
	 */
	public static function removeXSS($val) {
		// remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
		// this prevents some character re-spacing such as <java\0script>
		// note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
		$val = preg_replace('/([\x00-\x08]|[\x0b-\x0c]|[\x0e-\x19])/', '', $val);
		
		// straight replacements, the user should never need these since they're normal characters
		// this prevents like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61&#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29>
		$search = 'abcdefghijklmnopqrstuvwxyz';
		$search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$search .= '1234567890!@#$%^&*()';
		$search .= '~`";:?+/={}[]-_|\'\\';
		
		for ($i = 0; $i < strlen($search); $i++) {
			// ;? matches the ;, which is optional
			// 0{0,7} matches any padded zeros, which are optional and go up to 8 chars
			
			// &#x0040 @ search for the hex values
			$val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val); // with a ;
			// &#00064 @ 0{0,7} matches '0' zero to seven times
			$val = preg_replace('/(&#0{0,8}'.ord($search[$i]).';?)/', $search[$i], $val); // with a ;
		}
		
		// now the only remaining whitespace attacks are \t, \n, and \r
		$ra1 = Array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed'
			, 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
		$ra2 = Array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut'
			, 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate'
			, 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut'
			, 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend'
			, 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange'
			, 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete'
			, 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover'
			, 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange'
			, 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted'
			, 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
		$ra = array_merge($ra1, $ra2);
		
		$found = true; // keep replacing as long as the previous round replaced something
		while ($found == true) {
			$val_before = $val;
			for ($i = 0; $i < sizeof($ra); $i++) {
				$pattern = '/';
				for ($j = 0; $j < strlen($ra[$i]); $j++) {
					if ($j > 0) {
						$pattern .= '(';
						$pattern .= '(&#[xX]0{0,8}([9ab]);)';
						$pattern .= '|';
						$pattern .= '|(&#0{0,8}([9|10|13]);)';
						$pattern .= ')*';
					}
					$pattern .= $ra[$i][$j];
				}
				$pattern .= '/i';
				$replacement = substr($ra[$i], 0, 2).'<x>'.substr($ra[$i], 2); // add in <> to nerf the tag
				$val = preg_replace($pattern, $replacement, $val); // filter out the hex tags
			
				if ($val_before == $val) {
					// no replacements were made, so exit the loop
					$found = false;
				}
			}
		}
		
		return $val;
	}


	public static function ascii_to_entities($str) {
		$count	= 1;
		$out	= '';
		$temp	= array();
		
		for ($i = 0, $s = strlen($str); $i < $s; $i++) {
			$ordinal = ord($str[$i]);
			if ($ordinal < 128) {
				/*
					If the $temp array has a value but we have moved on, then it seems only
					fair that we output that entity and restart $temp before continuing. -Paul
				*/
				if (count($temp) == 1) {
					$out  .= '&#'.array_shift($temp).';';
					$count = 1;
				}
				$out .= $str[$i];
			} else {
				if (count($temp) == 0) {
					$count = ($ordinal < 224) ? 2 : 3;
				}
				$temp[] = $ordinal;
				if (count($temp) == $count) {
					$number = ($count == 3) ? (($temp['0'] % 16) * 4096) + (($temp['1'] % 64) * 64) + ($temp['2'] % 64) : (($temp['0'] % 32) * 64) + ($temp['1'] % 64);
					$out .= '&#'.$number.';';
					$count = 1;
					$temp = array();
				}
			}
		}
		
		return $out;
	}

	public static function entities_to_ascii($str, $all = TRUE) {
		if (preg_match_all('/\&#(\d+)\;/', $str, $matches)) {
			for ($i = 0, $s = count($matches['0']); $i < $s; $i++) {
				$digits = $matches['1'][$i];
				$out = '';
				if ($digits < 128) {
					$out .= chr($digits);
				} elseif ($digits < 2048) {
					$out .= chr(192 + (($digits - ($digits % 64)) / 64));
					$out .= chr(128 + ($digits % 64));
				} else {
					$out .= chr(224 + (($digits - ($digits % 4096)) / 4096));
					$out .= chr(128 + ((($digits % 4096) - ($digits % 64)) / 64));
					$out .= chr(128 + ($digits % 64));
				}
				$str = str_replace($matches['0'][$i], $out, $str);
			}
		}
		
		if ($all) {
			$str = str_replace(array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;", "&#45;"),
								array("&","<",">","\"", "'", "-"),
								$str);
		}
		
		return $str;
	}

	public static function highlight_phrase($str, $phrase, $tag_open = '<strong>', $tag_close = '</strong>') {
		if ($str == '') {
			return '';
		}
		if ($phrase != '') {
			return preg_replace('/('.preg_quote($phrase, '/').')/i', $tag_open."\\1".$tag_close, $str);
		}
		
		return $str;
	}

	public static function highlight_code($str) {
		// The highlight string function encodes and highlights
		// brackets so we need them to start raw
		$str = str_replace(array('&lt;', '&gt;'), array('<', '>'), $str);
		// Replace any existing PHP tags to temporary markers so they don't accidentally
		// break the string out of PHP, and thus, thwart the highlighting.
		$str = str_replace(array('<?', '?>', '<%', '%>', '\\', '</script>'),
							array('phptagopen', 'phptagclose', 'asptagopen', 'asptagclose', 'backslashtmp', 'scriptclose'), $str);
		// The highlight_string function requires that the text be surrounded
		// by PHP tags, which we will remove later
		$str = '<?php '.$str.' ?>'; // <?
		// All the magic happens here, baby!
		$str = highlight_string($str, TRUE);
		// Prior to PHP 5, the highligh function used icky <font> tags
		// so we'll replace them with <span> tags.
		if (abs(PHP_VERSION) < 5) {
			$str = str_replace(array('<font ', '</font>'), array('<span ', '</span>'), $str);
			$str = preg_replace('#color="(.*?)"#', 'style="color: \\1"', $str);
		}
		// Remove our artificially added PHP, and the syntax highlighting that came with it
		$str = preg_replace('/<span style="color: #([A-Z0-9]+)">&lt;\?php(&nbsp;| )/i', '<span style="color: #$1">', $str);
		$str = preg_replace('/(<span style="color: #[A-Z0-9]+">.*?)\?&gt;<\/span>\n<\/span>\n<\/code>/is', "$1</span>\n</span>\n</code>", $str);
		$str = preg_replace('/<span style="color: #[A-Z0-9]+"\><\/span>/i', '', $str);
		
		// Replace our markers back to PHP tags.
		$str = str_replace(array('phptagopen', 'phptagclose', 'asptagopen', 'asptagclose', 'backslashtmp', 'scriptclose'),
							array('&lt;?', '?&gt;', '&lt;%', '%&gt;', '\\', '&lt;/script&gt;'), $str);
		
		return $str;
	}



}