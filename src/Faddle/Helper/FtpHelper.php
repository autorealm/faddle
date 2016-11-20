<?php namespace Faddle\Helper;

class FtpUpload {

	private $rootPath;
	private $error = ''; //上传错误信息
	private $link; //FTP连接

	private $config = array(
		'host'     => '', //服务器
		'port'     => 21, //端口
		'timeout'  => 90, //超时时间
		'username' => '', //用户名
		'password' => '', //密码
	);

	/**
	 * 构造函数
	 */
	public function __construct($config) {
		/* 默认FTP配置 */
		$this->config = array_merge($this->config, $config);
		
		/* 登录FTP服务器 */
		if(!$this->login()){
			throw new \Exception($this->error);
		}
		
		/* 设置根目录 */
		$this->rootPath = ftp_pwd($this->link) . '/' . ltrim($root, '/');
	}

	/**
	 * 检测上传目录
	 */
	public function checkSavePath($savepath) {
		/* 检测并创建目录 */
		if (!$this->mkdir($savepath)) {
			return false;
		} else {
			//TODO:检测目录是否可写
			return true;
		}
	}

	/**
	 * 保存指定文件
	 */
	public function save($file, $replace=true) {
		$filename = $this->rootPath . $file['savepath'] . $file['savename'];
		/* 不覆盖同名文件 */
		if (!$replace && is_file($filename)) {
			$this->error = '存在同名文件' . $file['savename'];
			return false;
		}
		/* 移动文件 */
		if (!ftp_put($this->link, $filename, $file['tmp_name'], FTP_BINARY)) {
			$this->error = '文件上传保存错误！';
			return false;
		}
		return true;
	}

	/**
	 * 删除文件
	 */
	function delete($path) {
		if (@ftp_delete($this->link, $path)) {
			return true;
		} else {
			$this->error = '文件删除失败，请检查权限及路径是否正确！';
			return false;
		}
	}

	/**
	 * 移动文件
	 */
	function move($path, $newpath, $replace=true) {
		if (!$this->mkdirs($newpath)) {
			return false;
		}
		if (!$replace && is_file($newpath)) {
			$this->error = '存在同名文件' . basename($newpath);
			return false;
		}
		if (!@ftp_rename($this->conn_id, $path, $newpath)) {
			$this->error = '文件移动失败，请检查权限及原路径是否正确！';
			return false;
		}
		return true;
	}

	/**
	 * 获取文件
	 */
	function get($path, $getfile) {
		if (!@ftp_get($this->link, $getfile, $path, FTP_BINARY)) {
			$this->error = '文件复制失败，请检查权限及原路径是否正确！';
			return false;
		}
		return true;
	}

	/**
	 * 创建目录
	 */
	public function mkdir($savepath) {
		$dir = $this->rootPath . $savepath;
		if (ftp_chdir($this->link, $dir)) {
			return true;
		}
		
		if (ftp_mkdir($this->link, $dir)) {
			return true;
		} elseif ($this->mkdir(dirname($savepath)) && ftp_mkdir($this->link, $dir)) {
			return true;
		} else {
			$this->error = "目录 {$savepath} 创建失败！";
			return false;
		}
	}

	/**
	 * 生成目录
	 */
	function mkdirs($path) {
		$path = str_replace('\\', '/', $path);
		$path_arr = explode('/', $path); // 取目录数组
		$file_name = array_pop($path_arr); // 弹出文件名
		$path_div = count($path_arr); // 取层数
		foreach($path_arr as $val) {
			if ($val == '..' || $val == '.' || trim($val) == '')
				continue;
			if (@ftp_chdir($this->link, $val) == FALSE) {
				$tmp = @ftp_mkdir($this->link, $val);
				if ($tmp == FALSE) {
					$this->error = "目录 {$val} 创建失败！";
					return false;
				}
				@ftp_chdir($this->link, $val);
			}
		}
		for ($i=1; $i<=$path_div; $i++) {
			@ftp_cdup($this->conn_id);
		}
		return true;
	}

	/**
	 * 登录到FTP服务器
	 */
	private function login(){
		extract($this->config);
		$this->link = ftp_connect($host, $port, $timeout);
		if($this->link) {
			if (ftp_login($this->link, $username, $password)) {
				@ftp_pasv($this->link, 1); //打开被动模拟
				return true;
			} else {
				$this->error = "无法登录到FTP服务器：username - {$username}";
			}
		} else {
			$this->error = "无法连接到FTP服务器：{$host}";
		}
		return false;
	}

	/**
	 * 析构方法，用于断开当前FTP连接
	 */
	public function __destruct() {
		ftp_close($this->link);
	}

}
