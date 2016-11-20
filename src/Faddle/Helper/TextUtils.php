<?php namespace Faddle\Helper;

/**
 * 文本工具类
 */
class TextUtils {

	public static function sanitize($source) {
		$source = htmlspecialchars($source, ENT_COMPAT, 'UTF-8', false);
		return $source;
	}
	
	public static function unsanitize($str) {
		return htmlspecialchars_decode($str, ENT_COMPAT);
	}
	
	public static function base64url_encode($data) {
		return strtr(rtrim(base64_encode($data), '='), '+/', '-_');
	}
	
	public static function base64url_decode($data) {
		return base64_decode(strtr($data, '-_', '+/'));
	}
	
	/**
	 * Equivalent to htmlspecialchars(), but allows &#[0-9]+ (for unicode)
	 * @param string $str
	 * @return string
	 */
	public static function clean($str) {
		$str = preg_replace('/&(?!#[0-9]+;)/s', '&amp;', $str);
		$str = str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $str);
		return $str;
	}

	public static function start_with($string, $niddle) {
		return strncmp($string, $niddle, strlen($niddle)) === 0;
	}

	public static function end_with($string, $niddle) {
		return substr($string, strlen($string) - strlen($niddle), strlen($niddle)) == $niddle;
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
	 * 产生随机字符串
	 *
	 * @param int $length 输出长度 ，默认为 12
	 * @param string $chars 可选的 ，默认为 0123456789
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

	public static function random_cnchr($string, $length=3) {
		$cnchr = '';
		for ($i = 0; $i < $length; $i++) {
			$unidec = rand(19968, 24869);
			$unichr = '&#' . $unidec . ';';
			$cnchr .= mb_convert_encoding($unichr, 'UTF-8', 'HTML-ENTITIES');
		}
		return $cnchr;
	}
	
	/**
	 * 过滤 HTML
	 */
	public static function clear_html($html, $br=true) {
		$html = htmlspecialchars(trim($html));
		$html = str_replace("\t", ' ', $html);
		if ($br) {
			return nl2br($html);
		} else {
			return str_replace(array("\r\n", "\n"), '', $html);
		} 
	}

	public static function strip_comments($content) {
		$regex = '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/Uis';
		return preg_replace($regex, '', $content);
	}

	public static function auto_p($str, $br = true) {
		if (($str = trim($str)) === '') return '';
		
		$str = str_replace(array("\r\n", "\r"), "\n", $str);
		
		$str = preg_replace('~^[ \t]+~m', '', $str);
		$str = preg_replace('~[ \t]+$~m', '', $str);
		
		if ($html_found = (strpos($str, '<') !== false)) {
			$no_p = '(?:p|div|h[1-6r]|ul|ol|li|blockquote|d[dlt]|pre|t[dhr]|t(?:able|body|foot|head)|c(?:aption|olgroup)|form|s(?:elect|tyle)|a(?:ddress|rea)|ma(?:p|th))';
			
			$str = preg_replace('~^<'.$no_p.'[^>]*+>~im', "\n$0", $str);
			$str = preg_replace('~</'.$no_p.'\s*+>$~im', "$0\n", $str);
		}
		
		$str = '<p>'.trim($str).'</p>';
		$str = preg_replace('~\n{2,}~', "</p>\n\n<p>", $str);
		
		if ($html_found !== false) {
			$str = preg_replace('~<p>(?=</?'.$no_p.'[^>]*+>)~i', '', $str);
			$str = preg_replace('~(</?'.$no_p.'[^>]*+>)</p>~i', '$1', $str);
		}
		
		if ($br) {
			$str = preg_replace('~(?<!\n)\n(?!\n)~', "<br />\n", $str);
		}
		
		return $str;
	}

	public static function auto_a($text) {
		$search = array(
			'/(?<!")((h?ttps?|ftp):\/\/[^\s\"\)\<]*)/i',
			'/([a-zA-Z0-9_\-\.]+)@([a-z0-9\-]+(\.[a-z0-9\-]+)+)/'
		);
		$replace = array(
			"<a href=\"$1\" rel=\"nofollow\" target=\"_blank\">$1</a>",
			"<a href=\"mailto:$0\" rel=\"nofollow\">$1</a>"
		);
		$text = preg_replace($search, $replace, $text);
		
		return $text;
	}

	public static function remove_links($data, $eof="\n") {
		$lines = explode($eof, $data);
		while (list ($key, $line) = each ($lines)) {
			$line = eregi_replace("([ \t]|^)www\.", " http://www.", $line);
			$line = eregi_replace("([ \t]|^)ftp\.", " ftp://ftp.", $line);
			$line = eregi_replace("((http://|https://|ftp://|news://)[^ \)\r\n]+)", "", $line);
			$line = eregi_replace("([-a-z0-9_]+(\.[_a-z0-9-]+)*@([a-z0-9-]+(\.[a-z0-9-]+)+))", "", $line);
			if (empty($newText)) $newText = $line;
			else $newText .= $eof . $line;
		}
		return $newText;
	}

	/**
	 * 自闭合html修复函数
	 * 使用方法:
	 * <code>
	 * $input = '这是一段被截断的html文本<a href="#"';
	 * echo fix_html($input);
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
	public static function remove_xss($val) {
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

	public static function format_date($date, $format='Y-m-d') {
		if (is_string($date)) $date = strtotime($date);
		else $date = intval($date);
		return date($format, $date);
	}

	public static function format_size($size) {
		$sizes = array(' Bytes', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB');
		if ($size == 0) {
			return('N/A');
		} else {
			return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]); 
		}
	}

	public static function string_replace($string, $replacer) {
		$result = str_replace(array_keys($replacer), array_values($replacer),$string);
		return $result;
	}

	public static function encrypt_decrypt($string, $key, $decrypt=false) {
		if ($decrypt) {
			$decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($key))), '12');
			return $decrypted;
		} else {
			$encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $string, MCRYPT_MODE_CBC, md5(md5($key))));
			return $encrypted;
		}
	}

	/**
	 * escape
	 * @param $string
	 * @param $in_encoding
	 * @param $out_encoding
	 */
	public static function escape($string, $in_encoding='UTF-8', $out_encoding='UCS-2') {
		$return = '';
		if (function_exists('mb_get_info')) {
			for($x = 0; $x < mb_strlen($string, $in_encoding); $x ++) {
				$str = mb_substr($string, $x, 1, $in_encoding);
				if (strlen($str) > 1) {
					$return .= '%u' . strtoupper( bin2hex(mb_convert_encoding($str, $out_encoding, $in_encoding )));
				} else {
					$return .= '%' . strtoupper(bin2hex($str));
				}
			}
		}
		return $return;
	}
	public static function unescape($str) {
		$ret = '';
		$len = strlen($str);
		for ($i = 0; $i < $len; $i ++) {
			if ($str[$i] == '%' && $str[$i + 1] == 'u') {
				$val = hexdec(substr($str, $i + 2, 4));
				if ($val < 0x7f)
					$ret .= chr($val);
				else
					if ($val < 0x800)
						$ret .= chr(0xc0 | ($val >> 6)) .
						 chr(0x80 | ($val & 0x3f));
					else
						$ret .= chr(0xe0 | ($val >> 12)) .
						 chr(0x80 | (($val >> 6) & 0x3f)) .
						 chr(0x80 | ($val & 0x3f));
				$i += 5;
			} else {
				if ($str[$i] == '%') {
					$ret .= urldecode(substr($str, $i, 3));
					$i += 2;
				} else $ret .= $str[$i];
			}
		}
		return $ret;
	}

	public static function hex2rgb($hexColor) {
		$color = str_replace('#', '', $hexColor);
		if (strlen($color) == 6) {
			$rgb = array(
				'r' => hexdec(substr($color, 0, 2)),
				'g' => hexdec(substr($color, 2, 2)),
				'b' => hexdec(substr($color, 4, 2))
			);
		} else if (strlen($color) == 3) {
			$color = $hexColor;
			$r = substr($color, 0, 1) . substr($color, 0, 1);
			$g = substr($color, 1, 1) . substr($color, 1, 1);
			$b = substr($color, 2, 1) . substr($color, 2, 1);
			$rgb = array(
				'r' => hexdec($r),
				'g' => hexdec($g),
				'b' => hexdec($b)
			);
		} else {
			return false;
		}
		return $rgb;
	}

	
	/**
	 * 词义化时间
	 *
	 * @param string $from 起始时间
	 * @param string $now 终止时间
	 * @return string
	 */
	public static function sense_datediff($from, $now=null, $atime=true) {
		if (!isset($now)) $now = time();
		if (is_string($from)) $from = strtotime($from);
		if (is_string($now)) $now = strtotime($now);
		$from = intval($from);
		$now = intval($now);
		$between = $now - $from;
		
		if ($between >= 0 && $between < 86400 && date('d', $from) == date('d', $now)) { //如果是同一天
			if ($between < 3600) { //如果是一小时
				if ($between < 180) { //如果是三分钟
					return ('刚刚');
				}
				$min = ceil($between / 60);
				return ($min . '分钟前');
			}
			$hour = ceil($between / 3600);
			return ($hour . '小时前');
		}
		/** 如果是前一天 */
		if ($between > 0 && $between < 172800 && (date('z', $from) + 1 == date('z', $now) // 在同一年的情况 
			|| date('z', $from) + 1 == date('L') + 365 + date('z', $now))) { // 跨年的情况
			return ('昨天 ' . date('H:i', $from));
		}
		/** 如果是同星期 */
		if ($between > 0 && $between < 604800) {
			$day = ceil($between / 86400);
			return ($day .'天前'. date('H:i', $from));
		}
		/** 如果是同年 */
		if (date('Y', $from) == date('Y', $now)) {
			return $atime ? date('m月d日 H:i', $from) : date('n月j日', $from);
		}
		
		return $atime ? date('Y年m月d日 H:i', $from) : date('Y年m月d日', $from);
	}

	public static function sense_number($num) {
		if (! is_numeric($num)) return $num;
		if (intval($num) < 10000) return number_format($num);
		elseif (intval($num) < 100000000) return round(intval($num) / 10000, 2) . '万';
		else return number_format((intval($num) / 100000000), 2) . '亿';
	}

	public static function age($dob) {
		if (is_string($dob)) $dob = strtotime($dob);
		return (new \DateTime($dob))
				->diff(new \DateTime)
				->format("%y");
	}

	protected static $markdown_patterns = array(
		'\n(\s)*-' => '<br />&nbsp; &nbsp; &bullet; ',
		'\n(\s)*([0-9]+)\.?' => '<br />&nbsp; &nbsp; $1. ',
		'\[(info|warning|alert)\]([^\]]+)\[\/(info|warning|alert)\]' => '<div class="$1">$2</div>',
		'^|\s(\*\*|__)([^\*\*|__]+)(\*\*|__)' => '<strong>$2</strong>',
		'^|\s(\*|_)([^\*|_]+)(\*|_)' => '<em>$2</em>',
		'!\[([^\]]+)\]\(([^\)]+)\)' => '<img src="$2" alt="$1" />',
		'\[([^\]]+)\]\(([^\)]+)\)' => '<a href="$2">$1</a>',
		'\#{6}([^\n]+)\n' => '<h6>$1</h6>',
		'\#{5}([^\n]+)\n' => '<h5>$1</h5>',
		'\#{4}([^\n]+)\n' => '<h4>$1</h4>',
		'\#{3}([^\n]+)\n' => '<h3>$1</h3>',
		'\##([^\n]+)\n' => '<h2>$1</h2>',
		'\#([^\n]+)\n' => '<h1>$1</h1>',
	);
	
	
	public static function markdown($input) {
		foreach (self::$markdown_patterns as $search => $replace) {
			$input = preg_replace('/'.$search.'/i', $replace, $input);
		}
		
		// Handle Code
		$input = preg_replace_callback('/`([^`]+)`/i', function($matches) {
			return '<code><pre>'.htmlentities($matches[1]).'</pre></code>';
		}, $input);
		
		// Handle line breaks
		return str_replace("\n", "<br />", $input);
	}
	
	
	public static function markdown_clean($input) {
		foreach (self::$markdown_patterns as $search => $replace) {
			if (strpos($replace, '$2')) {
				$replace = '$2';
			} elseif (strpos($replace, '$1')) {
				$replace = '$1';
			} else {
				$replace = '';
			}
			
			$input = preg_replace('/'.$search.'/i', $replace, $input);
		}
		
		return str_replace("\n", "  ", $input);
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

	public static function debug_print($var, $depth=0, $length=40) {
		$_replace = array(
			"\n" => '<i>\n</i>',
			"\r" => '<i>\r</i>',
			"\t" => '<i>\t</i>'
			);
		
		switch (gettype($var)) {
			case 'array' :
				$results = '<b>Array (' . count($var) . ')</b>';
				foreach ($var as $curr_key => $curr_val) {
					$results .= '<br>' . str_repeat('&nbsp;', $depth * 2)
					. '<b>' . strtr($curr_key, $_replace) . '</b> =&gt; '
					. static::debug_print($curr_val, ++$depth, $length);
					$depth--;
				}
				break;
			case 'object' :
				$object_vars = get_object_vars($var);
				$results = '<b>' . get_class($var) . ' Object (' . count($object_vars) . ')</b>';
				foreach ($object_vars as $curr_key => $curr_val) {
					$results .= '<br>' . str_repeat('&nbsp;', $depth * 2)
					. '<b> -&gt;' . strtr($curr_key, $_replace) . '</b> = '
					. static::debug_print($curr_val, ++$depth, $length);
					$depth--;
				}
				break;
			case 'boolean' :
			case 'NULL' :
			case 'resource' :
				if (true === $var) {
					$results = 'true';
				} elseif (false === $var) {
					$results = 'false';
				} elseif (null === $var) {
					$results = 'null';
				} else {
					$results = htmlspecialchars((string) $var);
				}
				$results = '<i>' . $results . '</i>';
				break;
			case 'integer' :
			case 'float' :
				$results = htmlspecialchars((string) $var);
				break;
			case 'string' :
				$results = strtr($var, $_replace);
				if (strlen($var) > $length ) {
					$results = substr($var, 0, $length - 3) . '...';
				}
				$results = htmlspecialchars('"' . $results . '"');
				break;
			case 'unknown type' :
			default :
				$results = strtr((string) $var, $_replace);
				if (strlen($results) > $length ) {
					$results = substr($results, 0, $length - 3) . '...';
				}
				$results = htmlspecialchars($results);
		}
		
		return $results;
	}

}

?>