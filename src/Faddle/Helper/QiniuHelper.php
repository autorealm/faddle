<?php namespace Faddle\Helper;

if (! class_exists('\Qiniu\Auth')) {
	//require_once INCLUDE_PATH.'/Qiniu/functions.php';
	throw new \Exception('Can not find class: Qiniu');
}

use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;
use Qiniu\Processing\PersistentFop;
use Qiniu\Auth;

if (! defined('QINIU_ACCESS_KEY'))
define('QINIU_ACCESS_KEY', 'kNgRCQbi_88yhtzwrDJXtpcufhjPN5wAcXa3W7Yb');

if (! defined('QINIU_SECRET_KEY'))
define('QINIU_SECRET_KEY', 'kq50WRhog_KGe_K04NY-Q4ejFMOdrqkAh6HfavHy');

class QiniuHelper {

	const STORAGE_TYPE_FILE = 1;
	const STORAGE_TYPE_STRING = 2;
	private static $auth;
	private static $bkmgr;
	protected $handler;
	private $bucket = 'novious';
	private $token;
	private $config = array (
		'prefix' => '',
		'delimiter' => null,
		'expire' => 7200,
		'length' => 0,
		'bucket' => '',
		'path'  => '/assets/cache/',
		'access_key' => '',
		'secret_key' => ''
		);
	private $policy = array(
			'callbackUrl' => 'http://172.30.251.210/callback.php',
			'callbackBodyType' => 'application/json',
			'callbackBody' => '{"filename":$(fname), "filesize": $(fsize)}'
		);
	
	public function __construct() {
		$this->handler = new UploadManager();
		$this->with($this->config['bucket']);
	}
	
	public static function make() {
		return new self();
	}
	
	public function connect($options=array()) {
		if (is_array($options) and ! empty($options)) {
			$this->config = array_merge($this->config, $options);
			self::get_qiniu_auth($this->config['access_key'], $this->config['secret_key']);
			return $this->with($this->config['bucket']);
		}
		
		try {
			//return $this->handler->getBucketInfo($this->bucket);
		} catch (ErrorException $e) {
			//exit($e->getMessage());
			return false;
		}
		
	}

	public static function get_qiniu_auth($accessKey=null, $secretKey=null) {
		if (! $accessKey or ! $secretKey) {
			$accessKey = QINIU_ACCESS_KEY;
			$secretKey = QINIU_SECRET_KEY;
		} else {
			self::$auth = new Auth($accessKey, $secretKey);
		}
		if (! self::$auth) self::$auth = new Auth($accessKey, $secretKey);
		
		return self::$auth;
	}
	
	public static function get_bucket_manager($auth=null) {
		if (! $auth) {
			$auth = self::get_qiniu_auth();
		} else {
			self::$bkmgr = new BucketManager($auth);
		}
		if (! self::$bkmgr) self::$bkmgr = new BucketManager($auth);
		
		return self::$bkmgr;
	}
	
	public static function get_upload_token($bucket, $timeout=null, $policy=null) {
		$auth = self::get_qiniu_auth();
		$token = $auth->uploadToken($bucket, null, $timeout, $policy);
		
		return $token;
	}
	
	public static function get_signed_url($url) {
		$auth = self::get_qiniu_auth();
		// 私有空间中的外链 http://<domain>/<file_key>
		//$baseUrl = 'http://sslayer.qiniudn.com/1.jpg?imageView2/1/h/500';
		// 对链接进行签名
		$signed = $auth->privateDownloadUrl($baseUrl);
		
		return $signed;
	}
	
	public static function callback($url) {
		$auth = self::get_qiniu_auth();
		//获取回调的body信息
		$callbackBody = file_get_contents('php://input');
		//回调的contentType
		$contentType = 'application/x-www-form-urlencoded';
		//回调的签名信息，可以验证该回调是否来自七牛
		$authorization = $_SERVER['HTTP_AUTHORIZATION'];
		//七牛回调的url，具体可以参考：http://developer.qiniu.com/docs/v6/api/reference/security/put-policy.html
		//$url = 'http://172.30.251.210/callback.php';
		$isQiniuCallback = $auth->verifyCallback($contentType, $authorization, $url, $callbackBody);
		
		if ($isQiniuCallback) {
			return true;
		}
		return false;
	}
	
	public function query($limit=20, $prefix='', $start='') {
		$bmgr = self::get_bucket_manager();
		//$prefix = $this->config['prefix'];
		
		list($iterms, $start, $err) = $bmgr->listFiles($this->bucket, $prefix, $start, $limit);
		if ($err !== null) {
			return false;
		} else {
			return $iterms;
		}
		
	}
	
	/**
	 * 从指定URL抓取资源，并将该资源存储到指定空间中。
	 */
	public function fetch($key, $url, $bucket=null) {
		$bmgr = self::get_bucket_manager();
		if (! $bucket ) $bucket = $this->bucket;
		//$key = time() . '.ico';
		list($ret, $err) = $bmgr->fetch($url, $bucket, $key);
		
		if ($err !== null) {
			return false;
		} else {
			return $ret;
		}
	
	}
	
	public function get($key) {
		
	}
	
	public function put($key, $value, $type=self::STORAGE_TYPE_FILE) {
		if ($type == self::STORAGE_TYPE_FILE) {
			list($ret, $err) = $this->handler->putFile($this->token, $key, $value);
		} else if ($type == self::STORAGE_TYPE_STRING) {
			list($ret, $err) = $this->handler->put($this->token, $key, $value);
		}
		
		if ($err !== null) {
			return false;
		} else {
			return $ret;
		}
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
		
		$this->token = self::get_upload_token($bucket);
		
		return $this->handler;
	}
	
	public function remove($key) {
		$bmgr = self::get_bucket_manager();
		if (! $bucket ) $bucket = $this->bucket;
		$err = $bmgr->delete($bucket, $key);
		
		if ($err !== null) return false;
		return true;
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
	
	public function copy($from, $to, $bucket_from=null, $bucket_to=null) {
		$bmgr = self::get_bucket_manager();
		if (! $bucket_from ) $bucket_from = $this->bucket;
		if (! $bucket_to ) $bucket_to = $this->bucket;
		$err = $bmgr->copy($bucket_from, $from, $bucket_to, $to);
		if ($err !== null) return false;
		return true;
	}
	
	public function move($from, $to, $bucket_from=null, $bucket_to=null) {
		$bmgr = self::get_bucket_manager();
		if (! $bucket_from ) $bucket_from = $this->bucket;
		if (! $bucket_to ) $bucket_to = $this->bucket;
		$err = $bmgr->move($bucket_from, $from, $bucket_to, $to);
		if ($err !== null) return false;
		return true;
	}
	
	public function get_info($key, $bucket=null) {
		$bmgr = self::get_bucket_manager();
		if (! $bucket ) $bucket = $this->bucket;
		list($ret, $err) = $bmgr->stat($bucket, $key);
		
		if ($err !== null) {
			return false;
		} else {
			return $ret;
		}
	}
	
	public function get_url($key, $bucket=null) {
		if (! $bucket ) $bucket = $this->bucket;
		if ($bucket == 'novious') $domain = 'http://7xnbpc.com1.z0.glb.clouddn.com/';
		if ($bucket == 'sharedflow') $domain = 'http://7xjidm.com1.z0.glb.clouddn.com/';
		
		return $domain.$key;
	}
	
	public function pfop($key, $bucket=null) {
		if (! $bucket) $bucket = $this->bucket;
		$auth = self::get_qiniu_auth();
		//转码是使用的队列名称。 https://portal.qiniu.com/mps/pipeline
		$pipeline = 'abc';
		$pfop = new PersistentFop($auth, $bucket, $pipeline);

		//要进行转码的转码操作。 http://developer.qiniu.com/docs/v6/api/reference/fop/av/avthumb.html
		$fops = "avthumb/mp4/s/640x360/vb/1.25m";

		list($id, $err) = $pfop->execute($key, $fops);
		
		if ($err != null) {
			return $err;
		} else {
			//查询转码的进度和状态
			list($ret, $err) = $pfop->status($id);
			if ($err != null) {
				return $err;
			} else {
				return $ret;
			}
		}
		return false;
	}
	
	public function mkzip($key, $zipkey, $url, $bucket=null) {
		if (! $bucket) $bucket = $this->bucket;
		// 异步任务的队列， 去后台新建： https://portal.qiniu.com/mps/pipeline
		$pipeline = 'abc';
		
		$pfop = new PersistentFop($auth, $bucket, $pipeline);
		
		// 进行zip压缩的 $url
		// 压缩后的 $zipkey
		
		$fops = 'mkzip/1/url/' . \Qiniu\base64_urlSafeEncode($url1);
		$fops .= '|saveas/' . \Qiniu\base64_urlSafeEncode("$bucket:$zipkey");
		
		list($id, $err) = $pfop->execute($key, $fops);
		
		if ($err != null) {
			return $err;
		} else {
			return $id;
			//$res = "http://api.qiniu.com/status/get/prefop?id=$id";
			//echo "Processing result: $res";
		}
	}
	
	public function exists($key) {
		return ($this->get_info($key)) ? true : false;
	}
	
	public function time($key) {
		if ($info = ($this->get_info($key))) {
			$mtime = $info['putTime'];
			return $mtime;
		} else {
			return false;
		}
	}
	
	public function size($key) {
		if ($info = ($this->get_info($key))) {
			$msize = $info['fsize'];
			return $msize;
		} else {
			return false;
		}
	}
	
	public function type($key) {
		if ($info = ($this->get_info($key))) {
			$mtype = $info['mimeType'];
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
