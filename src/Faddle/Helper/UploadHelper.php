<?php namespace Faddle\Helper;

defined('TMP_PATH') or define('TMP_PATH', defined('SAE_TMPFS_PATH') ? SAE_TMPFS_PATH : sys_get_temp_dir());

use Faddle\Helper\QiniuHelper;

/**
 * PHP Image/File uploader with SaeStorage
 */
class UploadHelper {
	private $config = array(
			'mimes' => array(), // 允许上传的文件MiMe类型
			'maxSize' => 0, // 上传的文件大小限制 (0-不做限制)
			'exts' => array(), // 允许上传的文件后缀
			'domain' => 'uploads', // 保存域
			'autoSub' => false, // 自动子目录保存文件
			'subName' => array('date', 'Y-m-d'), // 子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
			'savePath' => 'files', // 保存路径
			'saveExt' => '', // 文件保存后缀，空则使用原后缀
			'replace' => true, // 存在同名是否覆盖
			'resize' => false, // 是否改变图片尺寸
			'hash' => false, // 是否生成hash编码
			'callback' => false, // 检测文件是否存在回调，如果存在返回文件信息数组
			'driver' => 'SaeUploader', // 文件上传驱动
	);
	
	private $error = ''; // 上传错误信息
	private $errors = array();
	private $uploader; // 上传驱动实例
	
	public function __construct($config=array()) {
		$this->config = array_merge($this->config, $config);
		$driver = $this->config['driver'];
		if ($driver and class_exists($driver))
			$this->uploader = new $driver($this->config['domain']);
		else
			$this->uploader = $this;
		
		if (!empty($this->config['mimes'])) {
			if (is_string($this->mimes)) {
				$this->config['mimes'] = explode(',', $this->mimes);
			}
			$this->config['mimes'] = array_map('strtolower', $this->mimes);
		}
		if (!empty($this->config['exts'])) {
			if (is_string($this->exts)) {
				$this->config['exts'] = explode(',', $this->exts);
			}
			$this->config['exts'] = array_map('strtolower', $this->exts);
		}
	}

	public function __get($name) {
		return $this->config[$name];
	}

	public function __set($name, $value) {
		if (isset($this->config[$name])) {
			$this->config[$name] = $value;
		}
	}

	public function __isset($name) {
		return isset($this->config[$name]);
	}

	/**
	 * 上传文件
	 */
	public function upload($files = '') {
		if ($files === '') {
			$files = $_FILES;
		}
		if (empty($files)) {
			$this->error = '没有上传的文件！';
			return false;
		}
		
		/* 检查上传目录 */
		if (!$this->uploader->checkSavePath($this->savePath)) {
			$this->error = $this->uploader->error;
			return false;
		}
		
		/* 逐个检测并上传文件 */
		$finfo = array();
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
		}
		
		foreach ($files as $key => $file) {
			if (!isset($file['key']))
				$file['key'] = $key;
			
			if (isset($finfo)) {
				$file['mime'] = finfo_file($finfo, $file['tmp_name']);
			}
			
			$file['ext'] = pathinfo($file['name'], PATHINFO_EXTENSION);
			
			/* 文件上传检测 */
			if (!$this->check($file)) {
				$this->errors[$file['name']] = $this->error;
				continue;
			}
			
			/* 获取文件hash */
			if ($this->hash) {
				$file['md5'] = md5_file($file['tmp_name']);
				$file['sha1'] = sha1_file($file['tmp_name']);
			}
			
			/* 生成保存文件名 */
			$savename = $this->getSaveName($file);
			if ($savename == false) {
				$this->error = '非法保存文件名！';
				$this->errors[$file['name']] = $this->error;
				continue;
			} else {
				$file['savename'] = $savename;
			}
			
			if ($this->autoSub and ($params = (array) $this->subName)) {
				if (! isset($params[1])) $params[1] = '';
				$subname = call_user_func_array($params[0], (array) $params[1]);
				$file['savepath'] = rtrim($this->savePath, '/') . '/' . trim($subname, '/') . '/';
			} else {
				$file['savepath'] = $this->savePath;
			}
			
			/* 对图像文件进行严格检测 */
			$ext = strtolower($file['ext']);
			if (in_array($ext, array('gif', 'jpg', 'jpeg', 'bmp', 'png'))) {
				$imginfo = getimagesize($file['tmp_name']);
				if (empty($imginfo) || ($ext == 'gif' && empty($imginfo['bits']))) {
					$this->error = '非法图像文件！';
					$this->errors[$file['name']] = $this->error;
					continue;
				}
			}
			
			/* 调用回调函数 */
			if ($this->callback) {
				$data = call_user_func($this->callback, $file);
			}
			
			/* 保存文件 并记录保存成功的文件 */
			if ($this->uploader->save($file, $this->replace)) {
				unset($file['error'], $file['tmp_name']);
				$info[$key] = $file;
			} else {
				$this->error = $this->uploader->error;
			}
		}
		if (isset($finfo)) {
			finfo_close($finfo);
		}
		
		return empty($info) ? false : $info;
	}

	/**
	 * 检查上传的文件
	 * @param array $file 文件信息
	 */
	private function check($file) {
		/* 文件上传失败，捕获错误代码 */
		if ($file['error']) {
			$this->error($file['error']);
			return false;
		}
		
		/* 无效上传 */
		if (empty($file['name'])) {
			$this->error = '未知上传错误！';
		}
		
		/* 检查是否合法上传 */
		if (!is_uploaded_file($file['tmp_name'])) {
			$this->error = '非法上传文件！';
			return false;
		}
		
		/* 检查文件大小 */
		if (($file['size'] > $this->maxSize) and ($this->maxSize != 0)) {
			$this->error = '上传文件大小不符！';
			return false;
		}
		
		/* 检查文件Mime类型 */
		if (!(empty($this->config['mimes']) ? true : in_array(strtolower($file['mime']), $this->mimes))) {
			$this->error = '上传文件MIME类型不允许！';
			return false;
		}
		
		/* 检查文件后缀 */
		if (!(empty($this->config['exts']) ? true : in_array(strtolower($file['ext']), $this->exts))) {
			$this->error = '上传文件后缀不允许';
			return false;
		}
		// if ((int) $file['error'] !== UPLOAD_ERR_OK) return true;
		/* 通过检测 */
		return true;
	}

	public static function valid_file($file) {
		return (isset($file['tmp_name']) 
				and isset($file['name']) 
				and isset($file['type']) 
				and isset($file['size']) 
				and is_uploaded_file($file['tmp_name']) 
				and (int) $file['error'] === UPLOAD_ERR_OK);
	}

	/**
	 * 获取错误代码信息
	 */
	private function error($errno) {
		switch ($errno) {
			case 1 :
				$this->error = '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值！';
			break;
			case 2 :
				$this->error = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值！';
			break;
			case 3 :
				$this->error = '文件只有部分被上传！';
			break;
			case 4 :
				$this->error = '没有文件被上传！';
			break;
			case 6 :
				$this->error = '找不到临时文件夹！';
			break;
			case 7 :
				$this->error = '文件写入失败！';
			break;
			default :
				$this->error = '未知上传错误！';
		}
	}

	private function getSaveName($file) {
		$filename = substr(pathinfo("_{$file['name']}", PATHINFO_FILENAME), 1);
		
		if ($_savename = md5_file($file['tmp_name'], true) or $_savename = uniqid())
			$savename = $_savename;
		else
			$savename = $filename;
		
		$ext = empty($this->config['saveExt']) ? $file['ext'] : $this->config['saveExt'];
		
		return $savename . '.' . $ext;
	}
	
	public static function get_extension($filename) {
		$x = explode('.', $filename);
		return '.'.end($x);
	}
	
	/**
	 * 检测上传目录
	 */
	public function checkSavePath($savepath) {
		/* 检测并创建目录 */
		if (!$this->mkdir($savepath)) {
			return false;
		} else {
			/* 检测目录是否可写 */
			if (!is_writable($savepath)) {
				$this->error = '上传目录 ' . $savepath . ' 不可写！';
				return false;
			} else {
				return true;
			}
		}
	}

	/**
	 * 保存文件
	 */
	public function save($file, $replace = true) {
		$file = is_array($file) ? $file : $_FILES[$file];
		
		if (!$file['savepath']) {
			$file['savepath'] = rtrim($this->savePath, '/') . '/';
		}
		if (!is_dir($file['savepath'])) {
			@mkdir($file['savepath'], 0777, true);
		}
		// if (! is_writable($file['savepath'])) throw new Exception('');
		
		if (!$file['savename']) {
			$file['savename'] = preg_replace('/\s+/', '_', ($file['name']));
		}
		
		$filename = $file['savepath'] . $file['savename'];
		
		/* 不覆盖同名文件 */
		if (! $replace && is_file($filename)) {
			$this->error = '存在同名文件' . $file['savename'];
			return false;
		}
		
		/* 移动文件 */
		if (is_uploaded_file($file['tmp_name']) and move_uploaded_file($file['tmp_name'], $filename)) {
			if ($chmod) {
				@chmod($filename, $chmod);
			}
			
			return $filename;
		}
		$this->error = '文件上传保存错误！';
		return false;
	}

	/**
	 * 创建目录
	 */
	public function mkdir($savepath) {
		if (is_dir($savepath)) {
			return true;
		}
		if (mkdir($savepath, 0777, true)) {
			return true;
		} else {
			$this->error = "目录 {$savepath} 创建失败！";
			return false;
		}
	}

	public function resize($file, $iWidth = 200, $iHeight = 200) {
		if (!is_uploaded_file($file['tmp_name'])) return false;
		// new unique filename
		$basename = basename($file['name']);
		//tempnam(sys_get_temp_dir(), '');
		$sTempFileName = rtrim(TMP_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '' . date("ymdHis") . rand();
		// move uploaded file into cache folder
		move_uploaded_file($file['tmp_name'], $sTempFileName);
		// change file permission to 644
		@chmod($sTempFileName, 0644);
		
		if (file_exists($sTempFileName) && filesize($sTempFileName) > 0) {
			$aSize = @getimagesize($sTempFileName); // try to obtain image info
			if (!$aSize) {
				@unlink($sTempFileName);
				return false;
			}
			
			// check for image type
			switch ($aSize[2]) {
				case IMAGETYPE_JPEG :
					$sExt = '.jpg';
					$vImg = @imagecreatefromjpeg($sTempFileName);// create a new image from file
				break;
				case IMAGETYPE_GIF :
					$sExt = '.gif';
					$vImg = @imagecreatefromgif($sTempFileName);
				break;
				case IMAGETYPE_PNG :
					$sExt = '.png';
					$vImg = @imagecreatefrompng($sTempFileName);
				break;
				default :
					@unlink($sTempFileName);
					return;
			}
			
			// create a new true color image
			$vDstImg = @imagecreatetruecolor($iWidth, $iHeight);
			$x1 = 0;
			$y1 = 0;
			$w = $aSize[0];
			$h = $aSize[1];
			if (isset($_POST['x1']) and isset($_POST['y1']) and isset($_POST['w']) and isset($_POST['h'])) {
				$x1 = (int) $_POST['x1'];
				$y1 = (int) $_POST['y1'];
				$w = (int) $_POST['w'];
				$h = (int) $_POST['h'];
			}
			// copy and resize part of an image with resampling
			imagecopyresampled($vDstImg, $vImg, 0, 0, $x1, $y1, $iWidth, $iHeight, $w, $h);
			
			// define a result image filename
			$sResultFileName = $sTempFileName . $sExt;
			// output image to file
			imagejpeg($vDstImg, $sResultFileName, 99);
			//@unlink($sTempFileName);
			return $sResultFileName;
		}
	}
}

/**
 * 新浪应用引擎文件上传器
 */
class SaeUploader {
	private $domain;
	public $error = ''; // 上传错误信息
	
	/**
	 * 构造函数
	 */
	public function __construct($domain = '') {
		$this->domain = $domain;
		if (!\Storage::getBucketInfo($domain)) {
			\Storage::putBucket($domain, \Storage::ACL_PUBLIC_READ);
		}
	}

	/**
	 * 检测上传目录
	 */
	public function checkSavePath($savepath) {
		return true;
	}

	/**
	 * 保存文件
	 */
	public function save($file, $replace = true) {
		$file = is_array($file) ? $file : $_FILES[$file];
		
		$bucketName = $this->domain;
		
		if (! $file['savepath']) {
			$file['savepath'] = '';
		} else {
			$file['savepath'] = rtrim($file['savepath'], '/') . '/';
		}
		
		if (!$file['savename']) {
			$file['savename'] = preg_replace('/\s+/', '_', ($file['name']));
		}
		
		$uploadName = $file['savepath'] . $file['savename'];
		
		if (! $replace) {
			if ($info = \Storage::getObjectInfo($bucketName, $uploadName))
				return $info;
		}
		
		$result = \Storage::putObject(\Storage::inputFile($file['tmp_name']), $bucketName, $uploadName);
		
		if ($result)
			$result = \Storage::getUrl($bucketName, $uploadName);
		
		return $result;
	}

}


/**
 * 七牛云存储上传器
 */
class QiniuUploader {
	private $driver;
	private $domain;
	public $error = ''; // 上传错误信息

	/**
	 * 构造函数
	 */
	public function __construct($domain = '') {
		$this->domain = $domain;
		$this->driver = QiniuHelper::make();
		$this->driver->with($this->domain);
		
	}

	/**
	 * 检测上传目录
	 */
	public function checkSavePath($savepath) {
		return true;
	}

	/**
	 * 保存文件
	 */
	public function save($file, $replace = true) {
		$file = is_array($file) ? $file : $_FILES[$file];

		$bucketName = $this->domain;

		if (! $file['savepath']) {
			$file['savepath'] = '';
		} else {
			$file['savepath'] = rtrim($file['savepath'], '/') . '/';
		}

		if (!$file['savename']) {
			$file['savename'] = preg_replace('/\s+/', '_', ($file['name']));
		}

		$uploadName = $file['savepath'] . $file['savename'];

		if (! $replace) {
			if ($info = $this->driver->get_info($uploadName))
				return $info;
		}

		$result = $this->driver->put($uploadName, $file['tmp_name']);

		if ($result)
			$result = $this->driver->get_url($uploadName, $bucketName);

		return $result;
	}

}