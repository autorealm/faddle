<?php namespace Faddle\Helper;

if (! class_exists('Upyun')) {
	throw new \Exception('Can not find class: Upyun');
}

use Upyun;

class UpyunHelper {
	const STORAGE_TYPE_FILE = 1;
	const STORAGE_TYPE_STRING = 2;
	protected $handler;
	private $bucket = '';
	private $config = array (
		'prefix' => '',
		'delimiter' => null,
		'expire' => 7200,
		'length' => 0,
		'bucket' => '',
		'path'  => '/assets/cache/',
		'username' => '',
		'password' => ''
		);
	
	public function __construct($options=array()) {
		if (is_array($options) and ! empty($options)) {
			$this->config = array_merge($this->config, $options);
		}
		$bucket = $this->config['bucket'];
		$user = $this->config['username'];
		$pwd = $this->config['password'];
		$this->bucket = $bucket;
		
		$upyun = new UpYun($bucket, $user, $pwd);
		$this->handler = $upyun;
	}
	
	public static function make() {
		return new self();
	}
	
	public function connect($username='', $password='') {
		if ($username and $password) {
			$this->handler->setOperator($username, $password);
			return true;
		}
		return false;
	}
	
	public function with($bucket=null) {
		if ($bucket) {
			$this->config['bucket'] = $bucket;
			$this->bucket = $this->config['bucket'];
		} else if ($this->config['bucket']) {
			$bucket = $this->config['bucket'];
			$this->bucket = $bucket;
		} else {
			$bucket = $this->bucket;
			$this->config['bucket'] = $bucket;
		}
		
		$this->handler->setBucket($bucket);
		
		return $this->handler;
	}
	
	public function query($path='/') {
		
		try {
			$list = $this->handler->getList($path);
			return $list;
		}
		catch(Exception $e) {
			//echo $e->getCode();
			//echo $e->getMessage();
		}
		return false;
	}
	
	public function get($key) {
		try {
			$resp = $this->handler->readFile($key);
			return $resp;
		} catch (Exception $e) {
			//echo $e->getCode();
			//echo $e->getMessage();
		}
		return false;
	}
	
	public function put($key, $value, $type=self::STORAGE_TYPE_FILE, $opts=null) {
		
		if ($type == self::STORAGE_TYPE_FILE) {
			if (! opts) {
				$opts = array(
					//UpYun::CONTENT_MD5 => md5(file_get_contents($value))
				);
			}
			$fh = fopen($value, 'rb');
			$rsp = $upyun->writeFile($key, $fh, True, $opts);
			fclose($fh);
			return $rsp;
		} else if ($type == self::STORAGE_TYPE_STRING) {
			$rsp = $upyun->writeFile($key, $value, True, $opts);
			
			return $rsp;
		}
		
	}
	
	public function fetch($key, $url, $opts=null) {
		
	}
	
	public function remove($key) {
		try {
			$resp = $this->handler->delete($key);
			return $resp;
		} catch (Exception $e) {
			//echo $e->getCode();
			//echo $e->getMessage();
		}
		return false;
	}

	public function clear() {
		$err = 0;
		while(true) {
			$ret = $this->query();
			if( $ret == false ){
				break;
			}
			foreach ($ret as $key => $value) {
				if (! $this->remove($key)) $err++;
			}
		}
		if ($err > 0) return false;
		return true;
	}

	public function get_info($key) {
		try {
			$resp = $this->handler->getFileInfo($key);
			return $resp;
		} catch (Exception $e) {
			//echo $e->getCode();
			//echo $e->getMessage();
		}
		return false;
	}
	
	public function get_url($key) {
		$bucket = $this->bucket;
		$domain = 'http://'.$bucket.'.b0.upaiyun.com/';
		return $domain.$key;
	}
	
	public function exists($key) {
		return ($this->get_info($key)) ? true : false;
	}
	
	public function time($key) {
		if ($info = ($this->get_info($key))) {
			$mtime = $info['file-date'];
			return $mtime;
		} else {
			return false;
		}
	}
	
	public function size($key) {
		if ($info = ($this->get_info($key))) {
			$msize = $info['file-size'];
			return $msize;
		} else {
			return false;
		}
	}
	
	public function type($key) {
		if ($info = ($this->get_info($key))) {
			$mtype = $info['file-type'];
			return $mtype;
		} else {
			return false;
		}
	}
	
	/**
	 * 队列缓存
	 */
	protected function queue($key, $value) {
		if (!$value) {
			$value = array();
		}
		if (!array_search($key, $value)) array_push($value, $key);
		if (count($value) > $this->config['length']) {
			$key = array_shift($value);
			$this->remove($key);
		}
		
		return true;
	}
	
	public function __call($method, $args) {
		if(method_exists($this->handler, $method)) {
			return call_user_func_array(array($this->handler,$method), $args);
		} else {
			return false;
		}
	}

}
