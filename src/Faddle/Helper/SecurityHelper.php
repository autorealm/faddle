<?php namespace Faddle\Helper;

if (!function_exists('mcrypt_encrypt')) {
    //throw new \RuntimeException("Use Cryptor need install php-mcrypt extension");
}

/**
 * 安全助手类
 */
class SecurityHelper {

	protected $options = array(
		'cipher' => MCRYPT_RIJNDAEL_256,
		'mode'   => MCRYPT_MODE_CBC,
		'block'  => 32,
		'rand'   => null,
		'key'    => null
	);

	protected $_iv_size;

	/**
	 * @param array|string $options
	 * @throws \InvalidArgumentException
	 *
	 *  new Cryptor('key')
	 *  new Cryptor(array('key' => 'test', 'cipher' => MCRYPT_RIJNDAEL_192))
	 */
	public function __construct($options = array())
	{
		if ($options) {
			if (is_array($options)) {
				$this->options = $options + $this->options;
			} else {
				$this->options['key'] = (string)$options;
			}
		}

		if (!$this->options['key']) throw new \InvalidArgumentException('Cryptor need key param');

		// Find the max length of the key, based on cipher and mode
		$size = mcrypt_get_key_size($this->options['cipher'], $this->options['mode']);

		if (isset($this->options['key'][$size])) {
			// Shorten the key to the maximum size
			$this->options['key'] = substr($this->options['key'], 0, $size);
		}

		// Store the IV size
		$this->_iv_size = mcrypt_get_iv_size($this->options['cipher'], $this->options['mode']);

		// Set the rand type if it has not already been set
		if ($this->options['rand'] === null) {
			if (defined('MCRYPT_DEV_URANDOM')) {
				// Use /dev/urandom
				$this->options['rand'] = MCRYPT_DEV_URANDOM;
			} elseif (defined('MCRYPT_DEV_RANDOM')) {
				// Use /dev/random
				$this->options['rand'] = MCRYPT_DEV_RANDOM;
			} else {
				// Use the system random number generator
				$this->options['rand'] = MCRYPT_RAND;
			}
		}
	}

	/**
	 * Encrypts a string and returns an encrypted string that can be decoded.
	 *
	 * The encrypted binary data is encoded using [base64](http://php.net/base64_encode)
	 * to convert it to a string. This string can be stored in a database,
	 * displayed, and passed using most other means without corruption.
	 *
	 * @param   string $data   data to be encrypted
	 * @return  string
	 */
	public function encrypt($data)
	{
		if ($this->options['rand'] === MCRYPT_RAND) {
			// The system random number generator must always be seeded each
			// time it is used, or it will not produce true random results
			mt_srand();
		}

		// Create a random initialization vector of the proper size for the current cipher
		$iv = mcrypt_create_iv($this->_iv_size, $this->options['rand']);

		// Encrypt the data using the configured options and generated iv
		$data = mcrypt_encrypt($this->options['cipher'], $this->options['key'], $data, $this->options['mode'], $iv);

		// Use base64 encoding to convert to a string
		return base64_encode($iv . $data);
	}

	/**
	 * Decrypts an encoded string back to its original value.
	 *
	 * @param   string $data   encoded string to be decrypted
	 * @return  bool|string
	 */
	public function decrypt($data)
	{
		// Convert the data back to binary
		$data = base64_decode($data, TRUE);

		if (!$data) {
			// Invalid base64 data
			return FALSE;
		}

		// Extract the initialization vector from the data
		$iv = substr($data, 0, $this->_iv_size);

		if ($this->_iv_size !== strlen($iv)) {
			// The iv is not the expected size
			return FALSE;
		}

		// Remove the iv from the data
		$data = substr($data, $this->_iv_size);

		// Return the decrypted data, trimming the \0 padding bytes from the end of the data
		return rtrim(mcrypt_decrypt($this->options['cipher'], $this->options['key'], $data, $this->options['mode'], $iv), "\0");
	}
	
	/**
	 * php DES解密函数
	 * 
	 * @param string $key 密钥
	 * @param string $encrypted 加密字符串
	 * @return string 
	 */
	function des_decode($key, $encrypted){
		$encrypted = base64_decode($encrypted);
		$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_CBC, ''); //使用MCRYPT_DES算法,cbc模式
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		$ks = mcrypt_enc_get_key_size($td);

		mcrypt_generic_init($td, $key, $key); //初始处理
		$decrypted = mdecrypt_generic($td, $encrypted); //解密
		
		mcrypt_generic_deinit($td); //结束
		mcrypt_module_close($td);
		return pkcs5_unpad($decrypted);
	} 
	/**
	 * php DES加密函数
	 * 
	 * @param string $key 密钥
	 * @param string $text 字符串
	 * @return string 
	 */
	function des_encode($key, $text){
		$y = pkcs5_pad($text);
		$td = mcrypt_module_open(MCRYPT_DES, '', MCRYPT_MODE_CBC, ''); //使用MCRYPT_DES算法,cbc模式
		$ks = mcrypt_enc_get_key_size($td);

		mcrypt_generic_init($td, $key, $key); //初始处理
		$encrypted = mcrypt_generic($td, $y); //解密
		mcrypt_generic_deinit($td); //结束
		mcrypt_module_close($td);
		return base64_encode($encrypted);
	} 
	function pkcs5_unpad($text){
		$pad = ord($text{strlen($text)-1});
		if ($pad > strlen($text)) return $text;
		if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return $text;
		return substr($text, 0, -1 * $pad);
	} 
	function pkcs5_pad($text, $block = 8){
		$pad = $block - (strlen($text) % $block);
		return $text . str_repeat(chr($pad), $pad);
	}

	public $default_key = 'a!takA:dlmcldEv,e';
	
	/**
	 * 字符加解密，一次一密,可定时解密有效
	 * 
	 * @param string $string 原文或者密文
	 * @param string $operation 操作(encode | decode)
	 * @param string $key 密钥
	 * @param int $expiry 密文有效期,单位s,0 为永久有效
	 * @return string 处理后的 原文或者 经过 base64_encode 处理后的密文
	 */
	public function encode($string,$key = '', $expiry = 3600){
		$ckey_length = 4;
		$key = md5($key ? $key : $this->default_key); //解密密匙
		$keya = md5(substr($key, 0, 16));		 //做数据完整性验证  
		$keyb = md5(substr($key, 16, 16));		 //用于变化生成的密文 (初始化向量IV)
		$keyc = substr(md5(microtime()), - $ckey_length);
		$cryptkey = $keya . md5($keya . $keyc);  
		$key_length = strlen($cryptkey);
		$string = sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string . $keyb), 0, 16) . $string;
		$string_length = strlen($string);

		$rndkey = array();	
		for($i = 0; $i <= 255; $i++) {	
			$rndkey[$i] = ord($cryptkey[$i % $key_length]);
		}

		$box = range(0, 255);	
		// 打乱密匙簿，增加随机性
		for($j = $i = 0; $i < 256; $i++) {
			$j = ($j + $box[$i] + $rndkey[$i]) % 256;
			$tmp = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}	
		// 加解密，从密匙簿得出密匙进行异或，再转成字符
		$result = '';
		for($a = $j = $i = 0; $i < $string_length; $i++) {
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$tmp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp; 
			$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
		}
		$result = $keyc . str_replace('=', '', base64_encode($result));
		$result = str_replace(array('+', '/', '='),array('-', '_', '.'), $result);
		return $result;
	}

	/**
	 * 字符加解密，一次一密,可定时解密有效
	 * 
	 * @param string $string 原文或者密文
	 * @param string $operation 操作(encode | decode)
	 * @param string $key 密钥
	 * @param int $expiry 密文有效期,单位s,0 为永久有效
	 * @return string 处理后的 原文或者 经过 base64_encode 处理后的密文
	 */
	public function decode($string,$key = '')
	{
		$string = str_replace(array('-', '_', '.'),array('+', '/', '='), $string);
		$ckey_length = 4;
		$key = md5($key ? $key : $this->default_key); //解密密匙
		$keya = md5(substr($key, 0, 16));		 //做数据完整性验证  
		$keyb = md5(substr($key, 16, 16));		 //用于变化生成的密文 (初始化向量IV)
		$keyc = substr($string, 0, $ckey_length);

		$cryptkey = $keya . md5($keya . $keyc);  
		$key_length = strlen($cryptkey);
		$string = base64_decode(substr($string, $ckey_length));
		$string_length = strlen($string);

		$result = '';
		$box = range(0, 255);
		$rndkey = array();	
		for($i = 0; $i <= 255; $i++) {	
			$rndkey[$i] = ord($cryptkey[$i % $key_length]);
		}
		// 打乱密匙簿，增加随机性
		for($j = $i = 0; $i < 256; $i++) {
			$j = ($j + $box[$i] + $rndkey[$i]) % 256;
			$tmp = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}
		// 加解密，从密匙簿得出密匙进行异或，再转成字符
		for($a = $j = $i = 0; $i < $string_length; $i++) {
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$tmp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp; 
			$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
		} 
		if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0)
		&& substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)
		) {
			return substr($result, 26);
		} else {
			return '';
		} 
	}

	public static function xml_encode( $string, $checkinput=false) {
		$string = str_replace( "\r\n", "\n", $string );
		$string = preg_replace( '/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $string );
		$string = htmlspecialchars( $string );
	
		if (!$checkinput) return $string;
	
		// 去除斜杠
		if (get_magic_quotes_gpc()) {
			$string = stripslashes($string);
		}
		$string = $this->_db->escape_string($string); //mysql_real_escape_string
		// 如果不是数字则加引号
		if (!is_numeric($string)) {
			$string = "'" . $string . "'";
		}
	
		return $string;
	}
	
	public static function clean_xss($string, $low = False) {
		if (! is_array( $string )) {
			$string = trim ( $string );
			$string = strip_tags ( $string );
			$string = htmlspecialchars ( $string );
			if ($low) return True;
				
			$string = str_replace( array ('"', "\\", "'", "/", "..", "../", "./", "//" ), '', $string );
			$no = '/%0[0-8bcef]/';
			$string = preg_replace( $no, '', $string );
			$no = '/%1[0-9a-f]/';
			$string = preg_replace( $no, '', $string );
			$no = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';
			$string = preg_replace( $no, '', $string );
			return True;
		}
		$keys = array_keys( $string );
		foreach ( $keys as $key ) {
			clean_xss( $string [$key] );
		}
	}
	
	/**
	 * 过滤XSS脚本危险字符
	 */
	public static function remove_xss($val) {
		// remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
		// this prevents some character re-spacing such as <java\0script>
		// note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
		$val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val);
		// straight replacements, the user should never need these since they're normal characters
		// this prevents like <IMG SRC=@avascript:alert('XSS')>
		$search = 'abcdefghijklmnopqrstuvwxyz';
		$search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$search .= '1234567890!@#$%^&*()';
		$search .= '~`";:?+/={}[]-_|\'\\';
		for ($i = 0; $i < strlen($search); $i++) {
			// ;? matches the ;, which is optional
			// 0{0,7} matches any padded zeros, which are optional and go up to 8 chars
			// @ @ search for the hex values
			$val = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $val); // with a ;
			// @ @ 0{0,7} matches '0' zero to seven times
			$val = preg_replace('/(�{0,8}'.ord($search[$i]).';?)/', $search[$i], $val); // with a ;
		}
		// now the only remaining whitespace attacks are \t, \n, and \r
		$ra1 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
		$ra2 = array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
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
						$pattern .= '|(�{0,8}([9|10|13]);)';
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

}
