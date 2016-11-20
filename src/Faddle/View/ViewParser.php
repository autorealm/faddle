<?php namespace Faddle\View;

use Faddle\View as View;
use Faddle\View\ViewEngine as ViewEngine;

/**
 * 模板解析类
 * @author KYO
 * @since 2015-9-21
 */
class ViewParser {
	private static $_instance = null;
	private $engine = null;
	private $content = '';
	public static $_prefix = '\{\{[\s]{0,}'; //一般语句前缀，转换由PHP处理
	public static $_suffix = '[\s]{0,}\}\}'; //一般语句后缀
	public static $_prefix_2 = '\{\%[\s]{0,}'; //指令语句前缀，通过模板解析处理
	public static $_suffix_2 = '[\s]{0,}\%\}'; //指令语句后缀
	public static $_prefix_3 = '\{#[\s]{0,}'; //注释语句前缀，解析时会直接清除
	public static $_suffix_3 = '[\s]{0,}#\}'; //注释语句后缀
	private $_patten = [];
	private $_match = [];
	private $_patten_all = '';
	private $_match_all = '';
	private $_patten_extends = '';
	private $_match_extends = '';
	private $_patten_yield = '';
	private $_patten_section = '';
	private $_patten_literal = '';
	private $_match_literal = '';
	private $_patten_comment = '';
	
	private $_patten_import = '';
	private $_patten_macro = '';
	private $_patten_filter = '';
	
	private static $sections = array();
	private static $literals = array(); //保存原样显示区块内容
	public static $gen_get = false; //开关? 无需变动
	public static $commands = array(
			'preprocess_mode' => 0, //预处理模式：1采用引擎解析或0直接PHP解析
			'macro_process_mode' => 0, //宏处理模式：1全量解析或0轻量解析
			'handle_variable' => 1, //检查并处理带点号的变量操作符
			'handle_filter' => 1, //检查并处理所有语句的过滤器
		);
	private $macros = array();
	
	protected $import_extensions = array(
		'gif' => 'data:image/gif',
		'png' => 'data:image/png',
		'jpe' => 'data:image/jpeg',
		'jpg' => 'data:image/jpeg',
		'jpeg' => 'data:image/jpeg',
		'svg' => 'data:image/svg+xml',
		'woff' => 'data:application/x-font-woff',
		'tif' => 'image/tiff',
		'tiff' => 'image/tiff',
		'xbm' => 'image/x-xbitmap',
	);
	
	
	public function __construct($contents, $engine=null) {
		if (isset($engine) and ($engine instanceof ViewEngine))
			$this->engine = $engine;
		$this->content = $contents;
		$this->init();
	}
	
	/**
	 * 加载并解析
	 */
	public static function load($content, $engine=null) {
		if (! isset(self::$_instance)) {
			self::$_instance = new self($content, $engine);
		} else {
			self::$_instance->content = $content;
		}
		return self::$_instance->parse();
	}
	
	private function init() {
		$_prefix = & self::$_prefix;
		$_suffix = & self::$_suffix;
		$_prefix_2 = & self::$_prefix_2;
		$_suffix_2 = & self::$_suffix_2;
		$_patten = & $this->_patten;
		$_match = & $this->_match;
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'if\s+([^\(].*)' .$_suffix. '/isU';
		$_match[] = "<?php if ($1) {?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'loop\s+\$([\w]+)\s+in\s+\$(\w[^\s]+)' .$_suffix. '/isU';
		$_match[] = "<?php \$faddle['loop']['$1']['iteration']=0; \$faddle['loop']['$1']['first']=true; \$faddle['loop']['$1']['last']=false; "
					."\$faddle['loop']['$1']['count']=count(\$$2); \$faddle['loop']['$1']['keys']=array_keys(\$$2); "
					."foreach (\$$2 as \$$1) { \$faddle['loop']['$1']['iteration']++; \$faddle['loop']['$1']['index']=\$faddle['loop']['$1']['iteration']-1; "
					."\$faddle['loop']['$1']['key']=\$faddle['loop']['$1']['keys'][\$faddle['loop']['$1']['index']]; "
					."if (\$faddle['loop']['$1']['iteration']>1) \$faddle['loop']['$1']['first']=false; "
					."if (\$faddle['loop']['$1']['iteration']==\$faddle['loop']['$1']['count']) \$faddle['loop']['$1']['last']=true; ?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'else' .$_suffix. '/i';
		$_match[] = "<?php } else {?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'else[\s]{0,}if[\s]{0,}(.*)' .$_suffix. '/isU';
		$_match[] = "<?php } elseif $1 {?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. '(?:end|\/)[\s]{0,}[A-Za-z]{0,}' .$_suffix. '/i';
		$_match[] = "<?php }?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'for\s+(\$\w+)\s+in\s+(.+)' .$_suffix. '/isU';
		$_match[] = "<?php foreach ($2 as $1) { ?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'foreach\s+(\$\w[^\s]+)\s+as\s+(\$.+)' .$_suffix. '/isU';
		$_match[] = "<?php foreach ($1 as $2) { ?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. 'foreach\s+\$(\w[^\s]+)(?:\s+as|)[\s]{0,}\([\$]{0,1}(\w+),[\s]{0,}[\$]{0,1}(\w+)\)' .$_suffix. '/isU';
		$_match[] = "<?php foreach (\$$1 as \$$2 => \$$3) { ?>";
		
		$_patten[] = '/[ \t]{0,}' .$_prefix. '(?:(if.*)|(foreach.*)|(for.*)|(switch.*)|(while.*)|(do.*))' .$_suffix. '/isU';
		$_match[] = "<?php $1 { ?>";
		
		//【注意】: 使用 ([\s\S]+) 比  (.*) 更耗时。
		$_patten[] = '/'.$_prefix. '(\$[A-Za-z_][^\s\|]+?)[\s]{1,}or[\s]{1,}(.+?)' .$_suffix. '/i';
		$_match[] = "<?php echo ($1) ?: $2;?>";
		
		$_patten[] = '/'.$_prefix. '(\$[A-Za-z_][^\s\|]+?)[\s]{1,}then[\s]{1,}(.+?)' .$_suffix. '/i';
		$_match[] = "<?php if ($1) echo $2;?>";
		
		$_patten[] = '/'.$_prefix. '(\$[A-Za-z_][^\s\|]+?)(?:\s([\>\<\=\!]{1,3}.+?)|)[\s]{1,}or[\s]{1,}(.+?)' .$_suffix. '/i';
		$_match[] = "<?php if ($1 $2) { echo $1; } else { echo $3; }?>";
		
		$_patten[] = '/'.$_prefix. '(\$[A-Za-z_][^\s\|]+?(?:[\s]{0,}\?.*?\:.+?|))' .$_suffix. '/';
		$_match[] = "<?php echo $1;?>";
		
		$_patten[] = '/'.$_prefix. 'set\s+(\$[A-Za-z_].+?)' .$_suffix. '/i';
		$_match[] = "<?php $1;?>";
		
		
		$this->_patten_all = '/'.$_prefix. '(.+?)' .$_suffix. '/';
		$this->_match_all = "<?php $1;?>";
		
		$this->_patten_extends = '/' .$_prefix_2. 'extends\s+file=[\"\']([\w\/\.\-\_\d]+)[\"\'](?:\s+as=[\"\'](.*)[\"\']|)' .$_suffix_2. '/iU';
		$this->_match_extends = '/<!--[\s]{0,}' .$_prefix_2. '#([\w\/\.\-\_\d]+)' .$_suffix_2. '[\s]{0,}-->/';
		
		$this->_patten_include = '/[ \t]{0,}' .$_prefix_2. 'include\s+file=[\"\']([\w\/\.\-\_\d]+)[\"\'](?:\s+(?:as|modifier)=[\"\'](\w+)[\"\']|)(?:\s+with=(\{.*\})|)(?:\s+(only)|).*' .$_suffix_2. '/iU';
		$this->_match_include = '';
		
		$this->_patten_yield = '/[ \t]{0,}' .$_prefix_2. 'yield\s+name=[\"\']([\w\.\-]+)[\"\'](?:\s+modifier=[\"\'](\w+)[\"\']|)' .$_suffix_2. '/i';
		$this->_patten_section = '/' .$_prefix_2. 'section\s+name=[\"\']([\w\.\-]+)[\"\']' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. '(?:end|\/)section' .$_suffix_2. '/i';
		
		$this->_patten_literal = '/' .$_prefix_2. 'literal(?:\s+as=[\"\'](.*?)[\"\']|)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. '(?:end|\/)literal' .$_suffix_2. '/i';
		$this->_match_literal = '/' .$_prefix_2. '(\$.+?)' .$_suffix_2. '/';
		
		$this->_patten_comment = '/' .self::$_prefix_3. '(.*)' .self::$_suffix_3. '/U';
		
		$this->_patten_import = '/' .$_prefix_2. 'import\s+file=[\"\']([\w\/\.\-\_\d]+)[\"\']\s+as=[\"\']([\w]+)[\"\']' .$_suffix_2. '/iU';
		$this->_patten_macro = '/' .$_prefix_2. 'macro\s+([\w]+)\((?:(.+)?|)\)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. '(?:end|\/)macro' .$_suffix_2. '/i';
		
		$this->_patten_filter = '/' .$_prefix. '(.*?)[\s]{0,}\|[\s]{0,}(\w+)(?:\:[\(]{0,1}(.*?)[\)]{0,1}|)(?:(\|.*)|)' .$_suffix. '/';
		
		$this->_patten_preprocess = '/' .$_prefix_2. 'preprocess(?:\s+command=[\"\'](.*?)[\"\']|)(?:\s+as=[\"\'](.*?)[\"\']|)' .$_suffix_2. '([\s\S]*?)' .$_prefix_2. '(?:end|\/)preprocess' .$_suffix_2. '/i';
		
	}
	
	/**
	 * 导入宏内容
	 */
	public static function import($body) {
		if (empty($body)) return [];
		if (isset(self::$_instance)) {
			$self = self::$_instance;
			$self->content = $body;
		} else {
			$self = new self($body);
			self::$_instance = $self;
		}
		$self->parse_macro();
		
		return $self->macros;
	}
	
	/**
	 * 处理语句块(仅解析为php语法部分)
	 */
	public static function process($body) {
		if (empty($body)) return '';
		if (isset(self::$_instance)) {
			$self = self::$_instance;
			$self->content = $body;
		} else {
			$self = new self($body);
			self::$_instance = $self;
		}
		$self->parse_comment();
		$self->parse_content();
		$self->parse_filter();
		$self->parse_all();
		
		return $self->content;
	}
	
	/**
	 * 解析模板内容
	 */
	public function parse($content=null) {
		if (! empty($content)) {
			$this->content = $content;
		}
		if (empty($this->content)) {
			trigger_error('未指定需要解析的视图内容。', E_USER_WARNING);
			return '';
		}
		//解析预处理内容标识
		$this->parse_preprocess();
		//解析原样显示内容标识
		$this->parse_literal();
		//解析模板注释内容标识
		$this->parse_comment();
		//解析模板区块内容标识
		$this->parse_section();
		//解析续承模板内容标识
		$this->parse_extends();
		//解析区块位置内容标识
		$this->parse_yield();
		//解析包含模板内容标识
		$this->parse_include();
		//解析引入宏文件内容标识
		$this->parse_import();
		//解析PHP处理内容标识
		$this->parse_content();
		//解析文本过滤函数内容标识
		$this->parse_filter();
		//解析扩展处理内容标识
		$this->parse_all();
		
		
		return $this->content;
	}

	/**
	 * <s>用于最后还原 literal 标识内容，需开启 $gen_get=true 并经过 parse() 解析。</s>
	 * @param string $content
	 * @return string
	 */
	public function gen_get($content) {
		if (preg_match_all($this->_match_literal, $content, $matchs, PREG_SET_ORDER)) {
			foreach ($matchs as $match) {
				$name = $match[1];
				$match = '/' . preg_quote($match[0], '/') . '/';
				if (array_key_exists($name, self::$literals))
					$content = preg_replace($match, self::$literals[$name], $content);
				else
					$content = preg_replace($match, '<!-- [literal error] : '.$name.' -->', $content);
			}
		}
		
		return $content;
	}

	private function parse_extends() {
		
		if (preg_match($this->_patten_extends, $this->content, $match)) {
			$this->content = preg_replace($this->_patten_extends, '', $this->content);
			//var_dump($match);
			if ($this->engine != null) $data = $this->engine->data();
			$temp = $match[1];
			$as = $match[2] ? $match[2] : '_vacancy';
			$match = '/' . preg_replace('/\//', '\/', $match[0]) . '/';
			$this->content = trim($this->content, "\r\n");
			$data[$as] = $this->content;
			$content = View::make( $temp, $data );
			
			if ($content) {
				if (preg_match($this->_match_extends, $content, $match)) {
					$this->content = preg_replace($this->_match_extends, $this->content, $content);
				} else {
					$content = $content ."\r\n". $this->content ."\r\n"; //加在父模板底部
					$this->content = $content;
				}
			} else {
				$this->content = preg_replace($match, '<!-- [extends failed] : '.$match.' -->', $this->content);
			}
		}
		
		return $this->content;
	}

	private function parse_section() {
		
		if (preg_match_all($this->_patten_section, $this->content, $matchs, PREG_SET_ORDER)) {
			foreach ($matchs as $match) {
				$name = $match[1];
				$content = $match[2] ?: '';
				//var_dump($content);
				if (isset(self::$sections[$name])) {
					continue; //主视图的 Section 不会被覆盖
				}
				self::$sections[$name] = $content;
			}
			
			//extract($sections, EXTR_OVERWRITE);
		}
		
		$this->content = preg_replace($this->_patten_section, '', $this->content);
		return $this->content;
	}

	private function parse_include() {
		
		$this->content = preg_replace_callback($this->_patten_include, function($match) {
			
			if ($this->engine != null) $data = $this->engine->data();
			$temp = $match[1];
			$as = (count($match)>2) ? $match[2] : '_placeholder';
			$render = false;
			if (stripos($match[0], 'modifier')) $render = $as;
			if (count($match)>3) {
				$with = json_decode($match[3], true) ?: [];
				$only = (count($match)>4) ? true : false;
				$match = '/' . preg_replace(['/\//', '/\$/', '/\./'], ['\/', '\$', '\.'], $match[0]) . '/';
				if ($only) $data = $with;
				else foreach($with as $k => $v) { // $data = array_merge($data, $with)
					$data[$k] = $v;
				}
			}
			$content = View::make( $temp , $data );
			//var_dump($data);
			if ($content) {
				/*extract(array(
						$as => $content
				), EXTR_OVERWRITE);*/
				if ($render and ViewEngine::modifier_exists($render)) {
					$result = ViewEngine::call_modifier($render, $content);
					if ($result) return $result;
				}
				return $content;
			} else {
				return '<!-- [include error] : '.$match.' -->';
			}
		}, $this->content);
		
		return $this->content;
	}

	private function parse_yield() {
		
		$this->content = preg_replace_callback($this->_patten_yield, function($match) {
			$name = $match[1];
			$render = (count($match)>2) ? trim($match[2]) : false;
			if (array_key_exists($name, self::$sections)) {
				if ($render and ViewEngine::modifier_exists($render)) {
					$result = ViewEngine::call_modifier($render, self::$sections[$name]);
					if ($result) return $result;
				}
				return self::$sections[$name];
			}
			return '';//$match[0];
		}, $this->content);
		//$this->content = preg_replace($this->_patten_yield, '', $this->content);
		
		/* 此方法效率低？
		if (preg_match_all($this->_patten_yield, $this->content, $matchs, PREG_SET_ORDER)) {
			$yields = array();
			foreach ($matchs as $match) {
				$name = $match[1];
				$match = '/' . preg_quote($match[0], '/') . '/';
				$this->content = preg_replace($match, self::$sections[$name], $this->content);
			}
			$this->content = preg_replace($this->_patten_yield, '', $this->content);
		}*/
		
		return $this->content;
	}

	/**
	 * 解析原样显示标识，未开启 gen_get 时仅能正确解析主内容模板，
	 * 开启后需调用 gen_get() 获取还原内容。
	 */
	private function parse_literal() {
		
		$this->content = preg_replace_callback($this->_patten_literal, function($match) {
			
			if (! empty($match[1])) {
				$as = $match[1];
			} else {
				$as = '_literal'. rand();
			}
			$content = trim($match[2], "\r\n");
			$match = $match[0];
			if (self::$gen_get == false) {
				if ($this->engine != null) $this->engine->assign_extras($as, $content, true);
				$to = '<?php echo $'. $as .' ;?>';
			} else {
				self::$literals[$as] = $content; //静态保存并留在最后页面生成时解析
				$to = ''.self::$_prefix_2. '$'.$as .self::$_suffix_2.'';
			}
			
			return $to;
		}, $this->content);
		
		return $this->content;
	}
	
	private function parse_import() {
		if (preg_match_all($this->_patten_import, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				$name = $match[1];
				$as = $match[2];
				$config = ($this->engine) ? $this->engine->config() : [];
				$engine = $this->engine;
				$body = ViewEngine::load($engine->exists($name, $engine->path(), $config['suffix']), '');
				$macros = ViewParser::import($body);
				//exit(var_dump($macros));
				
				if ($engine) {
					$engine->assign_macros($as, $macros); //注入宏
				}
			}
			
			$this->content = preg_replace($this->_patten_import, '', $this->content);
		}
		
		return $this->content;
	}
	
	private function parse_macro() {
		if (preg_match_all($this->_patten_macro, $this->content, $matchs, PREG_SET_ORDER)) {
			
			foreach ($matchs as $match) {
				$name = $match[1];
				$params = $match[2];
				$body = $match[3];
				$params = explode(',', $params);
				$_params = [];
				foreach ($params as $param) {
					$param = trim($param);
					$param = explode('=', $param);
					$param[0] = ltrim(trim($param[0], " \0\"\'\r\n"), '$');
					if (count($param) == 2) $param[1] = trim($param[1], " \0\"\'\r\n");
					else $param[1] = null;
					$_params[$param[0]] = $param[1];
				}
				//$params = array_flip($params);
				$this->macros[$name] = [$_params, $body];
			}
			
			$this->content = preg_replace($this->_patten_macro, '', $this->content);
		}
	
		return $this->content;
	}
	
	private function parse_comment() {
		$this->content = preg_replace($this->_patten_comment, '', $this->content);
		
		return $this->content;
	}
	
	private function parse_content() {
		
		$this->content = preg_replace_callback($this->_patten_all, function($match) {
			$var = '' . $match[1] . '';
			if (static::$commands['handle_variable']) self::handle_variable($var);
			if (static::$commands['handle_filter']) self::handle_filter($var);
			
			return str_replace($match[1], $var, $match[0]);
		}, $this->content);
		
		$this->content = preg_replace($this->_patten, $this->_match, $this->content);
		
		return $this->content;
	}
	
	private function parse_all() {
		
		if (! empty(ViewEngine::$extends)) { //扩展视图处理
			$this->content = preg_replace_callback($this->_patten_all, function($match) {
				$all = '' . $match[1] . '';
				//$match = '/' . preg_quote($match[0], '/') . '/';
				foreach (ViewEngine::$extends as $extend) {
					$rc = call_user_func($extend, $all);
					if (!isset($rc) or $rc == false or $rc == $all) continue;
					if (is_string($rc)) {
						return $rc;
					}
				}
				
				return $match[0];
			}, $this->content);
		}
		
		$this->content = preg_replace($this->_patten_all, $this->_match_all, $this->content);
		
		return  $this->content;
	}
	
	private function parse_filter() {
		
		$this->content = preg_replace_callback($this->_patten_filter, function($match) {
			list($_, $args, $name, $params, $mods) = $match;
			$ov = static::parse_filter_matchs($match);
			if ($ov) {
				
				return '<?php try{ echo @'.$ov.'; } catch(\Exception $e) { '
					. 'echo "<!-- [filter error] : {$e->getMessage()} -->"; } ?>';
				
			} else {
				return "<?php echo $args; ?>";
			}
			
		}, $this->content);
		
		return  $this->content;
	}
	
	/**
	 * 解析预处理
	 */
	private function parse_preprocess() {
		
		$this->content = preg_replace_callback($this->_patten_preprocess, function($match) {
			
			$command = $as = false;
			if (! empty($match[1])) $command = $match[1];
			if ($command and strlen(trim($command)) > 0) {
				$command = explode(',', $command);
				foreach ($command as $val) {
					$val = explode('=', $val); //提取指令
					if (count($val) == 2) static::$commands[trim($val[0])] = intval(trim($val[1]));
				}
				unset($val);
				unset($command);
			}
			if (! empty($match[2])) $as = $match[2];
			$body = trim($match[3]);
			if (static::$commands['preprocess_mode']) {
				$body = static::process($body); //语句块处理
			} else {
				$body = preg_replace('/<\?php\s+([\s\S]*?)[\s]{0,}\?\>/i', '$1', $body); //去掉php语句标识
				if (static::$commands['handle_variable']) self::handle_variable($body); //扩展处理变量
				if (static::$commands['handle_filter']) self::handle_filter($body); //扩展处理过滤器
				$body = "<?php\n" . $body . "\n?>";
			}
			ob_start() and ob_clean();
			//extract($args, EXTR_OVERWRITE);
			try {
				eval('?>'.$body);
			} catch (\Exception $e) {
				ob_end_clean(); throw $e;
			}
			$body = ob_get_clean(); //这里不做输出
			if ($this->engine and $as) $this->engine->assign_extras($as, $body);
			
			unset($match);
			unset($as);
			unset($body);
			if ($this->engine) $this->engine->assign_extras(get_defined_vars()); //将变量注入视图引擎
			
			return ''; //清空
			
		}, $this->content);
		
		return  $this->content;
	}

	/**
	 * 解析已匹配的过滤器
	 */
	protected static function parse_filter_matchs($matchs) {
		list($_, $args, $name, $params, $mods) = $matchs;
		$name = trim($name);
		if (! empty(ViewEngine::$modifiers) and ViewEngine::modifier_exists($name)) {
			//eval("\$args = \"$args\";");
			$params = $args . (empty($params) ? '' : ',' . $params);
			$ov = 'call_modifier(\''.$name.'\','. $params .')';
			if (! empty($mods)) {
				$mods = addcslashes($mods, '\'');
				$mods = preg_replace('/\|[\s]{0,}(\w+)(?:\:[\(]{0,1}([^\)\|]+)[\)]{0,1}|)/u', '\'$1\'=>\'$2\',', $mods);
				$mods = substr($mods, 0, -1);
				try {
					eval("\$mods = array($mods);");
					foreach ($mods as $name => $params) { //多层过滤
						$name = trim($name);
						$params = $ov . (empty($params) ? '' : ',' . $params);
						$ov = 'call_modifier(\''.$name.'\','. $params .')';
					}
				} catch (\ParseError $e) {
					trigger_error("模板过滤器[". var_export($mods, true)."]\r\n".sprintf('解析出错：%s', $e->getMessage())
							, E_USER_WARNING);
					return false;
				}
			}
			
			return $ov;
		} else {
			return false;
		}
	}

	/**
	 * 处理过滤器内容
	 * @param string
	 */
	public static function handle_filter(&$string) {
		$_string = trim($string);
		preg_replace_callback('/(\$\w+)[\s]{0,}\|[\s]{0,}(\w+)(?:\:[\(]{0,1}([^\(\)\|]+)[\)]{0,1}|)(?:(\|.*)|)/', 
		function($match) use (&$string, $_string) {
			list($_, $args, $name, $params, $mods) = $match;
			$ov = static::parse_filter_matchs($match);
			if ($ov) {
				//$as = '_filter'. rand();
				$result = ' @'.$ov.' ';
				if ($_string != $_)
				$string = str_replace($_, ' ('.$result.')', $string);
				else
				$string = str_replace($_, 'echo ('.$result.')', $string);
			} else {
				$string = str_replace($_, ''.$args.'', $string);
			}
			
		}, $_string);
		
	}

	/**
	 * 导入文件内容(base64-ized).
	 */
	protected function parse_import_files() {
		$extensions = array_keys($this->import_extensions);
		$regex = '/url\((["\']?)((?!["\']?data:).*?\.(' . implode('|', $extensions) . '))\\1\)/i';
		if ($extensions && preg_match_all($regex, $this->content, $matches, PREG_SET_ORDER)) {
			$search = array();
			$replace = array();
			
			foreach ($matches as $match) {
				$path = $match[2];
				//$path = dirname(STATIC_PATH) . '/' . $path;
				$extension = $match[3];
				
				if (strlen($path) < PHP_MAXPATHLEN && is_file($path) && is_readable($path) 
						&& ($size = @filesize($path)) && $size <= 10 * 1024) {
					// grab content && base64-ize
					$importContent = ViewEngine::load($path);
					$importContent = base64_encode($importContent);
					
					// build replacement
					$search[] = $match[0];
					$replace[] = 'url(' . $this->import_extensions[$extension] . ';base64,' . $importContent . ')';
				}
			}
			
			// replace the import statements
			$this->content = str_replace($search, $replace, $this->content);
		}
		
		return $this->content;
	}

	/**
	 * 清除模板解释引擎语句
	 */
	public function clean() {
		$this->content = preg_replace('/'.self::$_prefix. '(.+?)' .self::$_suffix. '/', '', $this->content);
		$this->content = preg_replace('/'.self::$_prefix_2. '(.+?)' .self::$_suffix_2. '/', '', $this->content);
		$this->content = preg_replace('/'.self::$_prefix_3. '(.+?)' .self::$_suffix_3. '/', '', $this->content);
		return $this->content;
	}

	/**
	 * 分段去除空白
	 */
	public static function trims($data) {
		$data = preg_replace('/(' . PHP_EOL . '([ |\t]+)?){2,}' . PHP_EOL . '/', PHP_EOL . PHP_EOL, trim($data));
		
		return $data;
	}
	
	/**
	 * 处理变量
	 */
	public static function handle_variable(&$string) {
		if (! preg_match('/\$[a-zA-Z0-9_]{1,}\.[^\s]{1,}/', $string)) return;
		$matchs = array();
		preg_match_all('/\$([a-zA-Z0-9_\.\$]+(?:(?:\-\>|[\.]{0,1})[\w]+\(.*\)|[a-zA-Z0-9_\.\$]+)+)/', $string, $matchs);
		if (!empty($matchs[0])) {
			foreach ($matchs[1] as $j => $var) {
				$_var_name = explode('.', $var);
				if (count($_var_name) > 1) {
					$vn = $_var_name[0];
					unset($_var_name[0]);
					$mod = array();
					foreach ($_var_name as $k => $index) {
						$index = explode('->', $index, 2);
						$obj = '';
						if (count($index) > 1) {
							$obj = '->'.$index[1];
						}
						$index = $index[0];
						if (substr($index, -1, 1) == ')') {
							$mod[] = $index.$obj;
							$obj = $index.$obj;
							$vn .= "->$obj";
						} else {
							if (substr($index,0,1) == '$' or is_numeric($index))
								$vn .= "[$index]$obj";
							else
								$vn .= "['$index']$obj";
						}
					}
					$_var_name = '$'.$vn;
				} else {
					$_var_name = '$'.$_var_name[0];
				}
				$string = str_replace(@$matchs[0][$j], ''.$_var_name.'', $string);
			}
		}
	}

}
