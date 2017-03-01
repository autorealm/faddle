<?php namespace Faddle\Common\Util;


class TextUtils {

	static $HC = array('∵', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…');
	static $EC = array(' ', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;');

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
	 * 字符串截取，支持中文和其他编码
	 *
	 * @param string $str 需要转换的字符串
	 * @param string $start 开始位置
	 * @param string $length 截取长度
	 * @param string $charset 编码格式
	 * @param string $suffix 截断显示字符
	 * @return string
	 */
	public static function ucutstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true) {
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
		$words = array(
			'javascript', 'expression', 'vbscript', 'script', 'base64',
			'applet', 'alert', 'document', 'write', 'cookie', 'window'
		);
		foreach ($words as $word) {
			$temp = '';
			for ($i = 0, $wordlen = strlen($word); $i < $wordlen; $i++) {
				$temp .= substr($word, $i, 1)."\s*";
			}
			// We only want to do this when it is followed by a non-word character
			// That way valid stuff like "dealer to" does not become "dealerto"
			$string = preg_replace_callback('#('.substr($temp, 0, -3).')(\W)#is', function($matches){
						return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
					}, $string);
		}
		
		$naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|'
				. 'ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';
		$string = preg_replace_callback('#<(/*\s*)('.$naughty.')([^><]*)([><]*)#is', function($matches){
					// encode opening brace
					$str = '&lt;'.$matches[1].$matches[2].$matches[3];
					// encode captured opening or closing brace to prevent recursive vectors
					return $str .= str_replace(array('>', '<'), array('&gt;', '&lt;'), $matches[4]);
				}, $string);
		
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
	 * 检查是否是可作为名称的字符串，仅允许出现单词汉字和有限的其他字符。
	 *
	 * @param $allowed string 可使用的字符，可多个
	 * @param $blacklist array 不可使用的名称数组，需统一小写字母（便于不区分大小写）
	 * @return boolean
	 */
	public static function is_nameable($str, $allowed='', $blacklist=array()) {
		$str = strtolower($str);
		$result = (! preg_match('/^[\w\x{00c0}-\x{FFFF}' . preg_quote($allowed, '/') . ']+$/iu', $str))
			or preg_match('/^[\d]+$/', $str) //一般不允许纯数字
			or in_array($str, $blacklist);
		return !$result;
	}

	public static function encode_html($str) {
		return str_replace(static::$HC, static::$EC, $str);
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
	 */
	public static function var2str($data) {
		return addslashes(var_export($data, true));
	}

	/**
	 * 转换字节数为其他单位
	 *
	 * @param string $filesize
	 * @return string
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
	 * 检查字符串是否是经过序列化的
	 * @param  mixed $data
	 * @return boolean
	 */
	public static function is_serialized($data) {
		// If it isn't a string, it isn't serialized
		if (!is_string($data)) {
			return false;
		}
		$data = trim($data);
		// Is it the serialized NULL value?
		if ($data === 'N;') {
			return true;
		} elseif ($data === 'b:0;' || $data === 'b:1;') {
			// Is it a serialized boolean?
			return true;
		}
		$length = strlen($data);
		// Check some basic requirements of all serialized strings
		if ($length < 4 || $data[1] !== ':' || ($data[$length - 1] !== ';' && $data[$length - 1] !== '}')) {
			return false;
		}
		
		return @unserialize($data) !== false;
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
	 * 字符串加密、解密函数
	 *
	 * @param string $txt
	 * @param string $operation
	 * @param string $key
	 * @param string $expiry
	 * @return string
	 *
	 */
	public static function sys_auth($string, $operation = 'ENCODE', $key = 'test', $expiry = 0) {
		$key_length = 4;
		$key = md5($key != '' ? $key : 'test');
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
	 * @param string $operation 'ENCODE' or 'DECODE'
	 * @param string $key
	 * @return string
	 *
	 */
	public static function sys_auth_ex($string, $operation = 'ENCODE', $key = 'test') {
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

	public static function ascii_to_entities($str) {
		$count	= 1;
		$out	= '';
		$temp	= array();
		
		for ($i = 0, $s = strlen($str); $i < $s; $i++) {
			$ordinal = ord($str[$i]);
			if ($ordinal < 128) {
				// If the $temp array has a value but we have moved on, then it seems only
				// fair that we output that entity and restart $temp before continuing. -Paul
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


	/**
	 * DES 解密函数
	 * 
	 * @param string $encrypted 加密字符串
	 * @param string $key 密钥
	 * @return string 
	 */
	function des_decode($encrypted, $key) {
		$encrypted = base64_decode($encrypted);
		$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_CBC, '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		$ks = mcrypt_enc_get_key_size($td);
		
		mcrypt_generic_init($td, $key, $key);
		$decrypted = mdecrypt_generic($td, $encrypted);
		
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		
		$pad = ord($decrypted{strlen($decrypted)-1});
		if ($pad > strlen($decrypted))
			return $decrypted;
		if (strspn($decrypted, chr($pad), strlen($decrypted) - $pad) != $pad)
			return $decrypted;
		return substr($decrypted, 0, -1 * $pad);
	}

	/**
	 * DES 加密函数
	 * 
	 * @param string $text 字符串
	 * @param string $key 密钥
	 * @return string 
	 */
	function des_encode($key, $text) {
		$block = 8;
		$pad = $block - (strlen($text) % $block);
		$y = $text . str_repeat(chr($pad), $pad);
		
		$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_CBC, '');
		$ks = mcrypt_enc_get_key_size($td);

		mcrypt_generic_init($td, $key, $key);
		$encrypted = mcrypt_generic($td, $y);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		return base64_encode($encrypted);
	} 

	public static function xor_encrypt($string, $key) {
		$str_len = strlen($string);
		$key_len = strlen($key);
		for ($i = 0; $i < $str_len; $i++) {
			for ($j = 0; $j < $key_len; $j++) {
				$string[$i] = $string[$i] ^ $key[$j];
			}
		}
		return $string;
	}

	public static function xor_decrypt($string, $key) {
		$str_len = strlen($string);
		$key_len = strlen($key);
		for ($i = 0; $i < $str_len; $i++) {
			for ($j = 0; $j < $key_len; $j++) {
				$string[$i] = $key[$j] ^ $string[$i];
			}
		}
		return $string;
	}

	/**
	 * Run a shell command
	 * 
	 * @param   string  command to run
	 * @return  string
	 */
	public static function run_command($command, $cwd=null, $envopts=array()) {
		$descriptorspec = array(
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$pipes = array();
		
		if (count($_ENV) === 0) {
			$env = NULL;
			foreach($envopts as $k => $v) {
				putenv(sprintf("%s=%s",$k,$v));
			}
		} else {
			$env = array_merge($_ENV, $envopts);
		}
		
		$resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);
		
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}
		
		$status = trim(proc_close($resource));
		if ($status) throw new \Exception($stderr);
		
		return $stdout;
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


	/**
	 * Indents a flat JSON string to make it more human-readable.
	 *
	 * @param string $json The original JSON string to process.
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

}