<?PHP namespace Faddle\Common\Util;


class FileUtils {

	/**
	 * 读取并返回指定文件的文本内容
	 *
	 * @access	public
	 * @param	string	path to file
	 * @return	string
	 */
	public static  function read_file($file) {
		if (!file_exists($file)) {
			return FALSE;
		}
		
		if (function_exists('file_get_contents')) {
			return file_get_contents($file);
		}
		
		if (!$fp = @fopen($file, FOPEN_READ)) {
			return FALSE;
		}
		
		flock($fp, LOCK_SH);
		
		$data = '';
		if (filesize($file) > 0) {
			$data = & fread($fp, filesize($file));
		}
		
		flock($fp, LOCK_UN);
		fclose($fp);
		
		return $data;
	}

	/**
	 * 将文本写入指定文件
	 *
	 * @access public
	 * @param string path to file
	 * @param string file data
	 * @return bool
	 */
	public static  function write_file($path, $data, $mode = FOPEN_WRITE_CREATE_DESTRUCTIVE) {
		if ( ! $fp = @fopen($path, $mode)) {
			return FALSE;
		}
		
		flock($fp, LOCK_EX);
		fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);
		
		return TRUE;
	}

	/**
	 * 删除文件或者文件夹
	 */
	public static function delete_files($path, $del_dir = FALSE, $level = 0) {
		// Trim the trailing slash
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		
		if ( ! $current_dir = @opendir($path)) {
			return FALSE;
		}
		
		while (FALSE !== ($filename = @readdir($current_dir))) {
			if ($filename != '.' and $filename != '..') {
				if (is_dir($path. DIRECTORY_SEPARATOR .$filename)) {
					// Ignore empty folders
					if (substr($filename, 0, 1) != '.') {
						static::delete_files($path. DIRECTORY_SEPARATOR .$filename, $del_dir, $level + 1);
					}
				} else {
					unlink($path. DIRECTORY_SEPARATOR .$filename);
				}
			}
		}
		@closedir($current_dir);
		
		if ($del_dir == TRUE AND $level > 0) {
			return @rmdir($path);
		}
		
		return TRUE;
	}

	/**
	 * 取得文件名
	 */
	public static function get_filename($source_dir, $include_path = FALSE, $_recursion = FALSE) {
		static $_filedata = array();
		
		if ($fp = @opendir($source_dir)) {
			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($_recursion === FALSE) {
				$_filedata = array();
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			}
			
			while (FALSE !== ($file = readdir($fp))) {
				if (@is_dir($source_dir . $file) && strncmp($file, '.', 1) !== 0) {
					static::get_filename($source_dir . $file . DIRECTORY_SEPARATOR, $include_path, TRUE);
				} elseif (strncmp($file, '.', 1) !== 0) {
					$_filedata[] = ($include_path == TRUE) ? $source_dir . $file : $file;
				}
			}
			return $_filedata;
		} else {
			return FALSE;
		}
	}

	/**
	 * 取得目录文件信息
	 */
	public static function get_dir_file_info($source_dir, $top_level_only = TRUE, $_recursion = FALSE) {
		static $_filedata = array();
		$relative_path = $source_dir;
		
		if ($fp = @opendir($source_dir)) {
			// reset the array and make sure $source_dir has a trailing slash on the initial call
			if ($_recursion === FALSE) {
				$_filedata = array();
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			}
			
			// foreach (scandir($source_dir, 1) as $file) // In addition to being PHP5+, scandir() is simply not as fast
			while (FALSE !== ($file = readdir($fp))) {
				if (@is_dir($source_dir . $file) and strncmp($file, '.', 1) !== 0 and $top_level_only === FALSE) {
					static::get_dir_file_info($source_dir . $file . DIRECTORY_SEPARATOR, $top_level_only, TRUE);
				} elseif (strncmp($file, '.', 1) !== 0) {
					$_filedata[$file] = static::get_file_info($source_dir . $file);
					$_filedata[$file]['relative_path'] = $relative_path;
				}
			}
			
			return $_filedata;
		} else {
			return FALSE;
		}
	}

	/**
	 * 取得文件信息
	 */
	public static function get_file_info($file, $returned_values = array('name', 'server_path', 'size', 'date')) {
		if (!file_exists($file)) {
			return FALSE;
		}
		
		if (is_string($returned_values)) {
			$returned_values = explode(',', $returned_values);
		}
		
		foreach ($returned_values as $key) {
			switch ($key) {
				case 'name' :
					$fileinfo['name'] = substr(strrchr($file, DIRECTORY_SEPARATOR), 1);
				break;
				case 'server_path' :
					$fileinfo['server_path'] = $file;
				break;
				case 'size' :
					$fileinfo['size'] = filesize($file);
				break;
				case 'date' :
					$fileinfo['date'] = filemtime($file);
				break;
				case 'readable' :
					$fileinfo['readable'] = is_readable($file);
				break;
				case 'writable' :
					// There are known problems using is_weritable on IIS. It may not be reliable - consider fileperms()
					$fileinfo['writable'] = is_writable($file);
				break;
				case 'executable' :
					$fileinfo['executable'] = is_executable($file);
				break;
				case 'fileperms' :
					$fileinfo['fileperms'] = fileperms($file);
				break;
			}
		}
		
		return $fileinfo;
	}

	/**
	 * 生成目录图数组
	 */
	public static function dir_map($source_dir, $directory_depth = 0, $hidden = FALSE) {
		if ($fp = @opendir($source_dir)) {
			$filedata	= array();
			$new_depth	= $directory_depth - 1;
			$source_dir	= rtrim($source_dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			
			while (FALSE !== ($file = readdir($fp))) {
				// Remove '.', '..', and hidden files [optional]
				if ( ! trim($file, '.') OR ($hidden == FALSE && $file[0] == '.')) {
					continue;
				}
				
				if (($directory_depth < 1 OR $new_depth > 0) && @is_dir($source_dir.$file)) {
					$filedata[$file] = static::dir_map($source_dir.$file.DIRECTORY_SEPARATOR, $new_depth, $hidden);
				} else {
					$filedata[] = $file;
				}
			}
			
			closedir($fp);
			return $filedata;
		}
		
		return FALSE;
	}

	/**
	 * 文件扫描
	 * @param $filepath 目录
	 * @param $subdir 是否搜索子目录
	 * @param $ex 搜索扩展
	 * @param $isdir 是否只搜索目录
	 * @param $enforcement 强制更新缓存
	 */
	public static function scan_files($filepath, $subdir = 1, $ex = '', $isdir = 0, $enforcement = 0) {
		static $file_list = array();
		if ($enforcement)
			$file_list = array();
		$filepath = (is_dir($filepath)) ? $filepath : dirname($filepath);
		$flags = $isdir ? GLOB_ONLYDIR : 0;
		$list = glob($filepath . '*' . (!empty($ex) && empty($subdir) ? '.' . $ex : ''), $flags);
		if (!empty($ex))
			$ex_num = strlen($ex);
		foreach ($list as $k => $v) {
			if ($subdir && is_dir($v)) {
				static::scan_files($v . DIRECTORY_SEPARATOR, $subdir, $ex, $isdir);
				continue;
			}
			if (!empty($ex) && strtolower(substr($v, -$ex_num, $ex_num)) != $ex) {
				unset($list[$k]);
				continue;
			} else {
				$file_list[dirname($v)][] = $v;
				continue;
			}
		}
		return $file_list;
	}


	/**
	 * 取得文件扩展
	 *
	 * @param $filename 文件名
	 * @return 扩展名
	 */
	public static function fileext($filename) {
		return strtolower(trim(substr(strrchr($filename, '.'), 1)));
	}

	/**
	 * 文件下载
	 * @param $filepath 文件完整路径
	 * @param $filename 文件下载名称
	 */
	public static function download_file($filepath, $filename = '') {
		if (!$filename)
			$filename = basename($filepath);
		if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) { // for IE
			$filename = rawurlencode($filename);
		}
		$filetype = fileext($filename);
		$filesize = sprintf("%u", filesize($filepath));
		if (ob_get_length() !== false)
			@ob_end_clean();
		header('Content-Description: File Transfer');
		header('Pragma: public');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: pre-check=0, post-check=0, max-age=0');
		header('Content-Transfer-Encoding: binary');
		header('Content-Encoding: none');
		header('Content-type: ' . $filetype);
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-length: ' . $filesize);
		readfile($filepath);
		exit();
	}

	/**
	 * Search include path for a given file
	 * @static
	 * @param string $file
	 * @return bool
	 */
	public static function search_include_path($file, $ps=null) {
		if (! isset($ps))
			$ps = explode(PATH_SEPARATOR, ini_get('include_path'));
		foreach ($ps as $path) {
			if (@file_exists($path . DIRECTORY_SEPARATOR . $file)) return true;
		}
		if (@file_exists($file)) return true;
		return false;
	}

	public static function safe_dirname($path) {
		return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", dirname($path)): dirname($path));
	}

	public static function safe_basename($path) {
		return (DIRECTORY_SEPARATOR === "\\" ? str_replace("\\", "/", basename($path)): basename($path));
	}

	public static function safe_pathname($path) {
		$path = str_replace('\\', '/', $path);
		if(substr($path, -1) != '/') $path = $path.'/';
		return $path;
	}

	/**
	 * 创建目录
	 * 
	 * @param	string	$path	路径
	 * @param	string	$mode	属性
	 * @return	string	如果已经存在则返回true，否则为flase
	 */
	public static function dir_create($path, $mode = 0777) {
		if(is_dir($path)) return TRUE;
		$ftp_enable = 0;
		$temp = explode('/', $path);
		$cur_dir = '';
		$max = count($temp) - 1;
		for ($i = 0; $i < $max; $i++) {
			$cur_dir .= $temp[$i].'/';
			if (@is_dir($cur_dir)) continue;
			@mkdir($cur_dir, 0777,true);
			@chmod($cur_dir, 0777);
		}
		return is_dir($path);
	}

	/**
	 * 拷贝目录及下面所有文件
	 * 
	 * @param	string	$fromdir	原路径
	 * @param	string	$todir		目标路径
	 * @return	string	如果目标路径不存在则返回false，否则为true
	 */
	public static function dir_copy($fromdir, $todir) {
		if (!is_dir($fromdir)) return FALSE;
		if (!is_dir($todir)) static::dir_create($todir);
		$fromdir = rtrim(realpath($fromdir), DIRECTORY_SEPARATOR);
		$todir = rtrim(realpath($todir), DIRECTORY_SEPARATOR);
		$list = glob($fromdir.'*');
		if (!empty($list)) {
			foreach($list as $v) {
				$path = $todir.basename($v);
				if(is_dir($v)) {
					static::dir_copy($v, $path);
				} else {
					@copy($v, $path);
					@chmod($path, 0777);
				}
			}
		}
		return TRUE;
	}

	/**
	 * 设置目录下面的所有文件的访问和修改时间
	 * 
	 * @param	string	$path		路径
	 * @param	int		$mtime		修改时间
	 * @param	int		$atime		访问时间
	 * @return	array	不是目录时返回false，否则返回 true
	 */
	public static function dir_touch($path, $mtime = TIME, $atime = TIME) {
		if (is_file($path)) {
			@touch($path, $mtime, $atime);
			return true;
		}
		if (!is_dir($path)) return false;
		$path = rtrim(realpath($path), DIRECTORY_SEPARATOR);
		$files = glob($path.'*');
		foreach($files as $v) {
			is_dir($v) ? static::dir_touch($v, $mtime, $atime) : @touch($v, $mtime, $atime);
		}
		return true;
	}

	/**
	 * Delete file recursively
	 *
	 * @param strin $dir
	 * @return bool
	 */
	public static function recursive_delete($dir) {
		foreach (array_diff(scandir($dir), ['.','..']) as $file) {
			(is_dir("$dir/$file")) ? self::recursiveDelete("$dir/$file") : @unlink("$dir/$file");
		}
		return @rmdir($dir);
	}
	
	/**
	 * Copy file recursively
	 * @param <type> src, Source
	 * @param string $dest, where to save
	 * @return <type>
	 */
	public static function recursive_copy($src,$dest) {
		// recursive function to delete
		// all subdirectories and contents:
		if(is_dir($src))$dir_handle = opendir($src);
			while ($file = readdir($dir_handle)) {
			if ($file!='.' && $file!='..') {
				if (!is_dir($src.'/'.$file)) {
					if (!file_exists($dest.'/'.$file))
						@copy($src.'/'.$file, $dest.'/'.$file);
				} else {
					@mkdir($dest.'/'.$file, 0775);
					self::recursiveCopy($src.'/'.$file, $dest.'/'.$file);
				}
			}
		}
		closedir($dir_handle);
		return true;
	}

	public static function file_info($path) {
		if (!file_exists($path)) {
			return false;
		}
		clearstatcache(false, $path);
		
		$stat = stat($path);
		$finfo = new \finfo(FILEINFO_MIME);
		$mimetype = $finfo->file($this->getPathname());
		$mimetypeParts = preg_split('/\s*[;,]\s*/', $mimetype);
		$mimetype = strtolower($mimetypeParts[0]);
		unset($finfo);
		if (preg_match('/^image\/*$/i', $mimetype)) {
			list($width, $height) = getimagesize($path);
		}
		$info = array(
			'path' => $path,
			'dir' => dirname($path),
			'name' =>  pathinfo($path, PATHINFO_FILENAME),
			'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
			'mimetype' => $mimetype,
			'md5' => md5_file($path),
			'is_dir' => is_dir($path),
			'is_file' => is_file($path),
			'is_readable' => is_readable($path),
			'is_writable' => is_writable($path),
			'is_uploaded_file' => is_uploaded_file($path),
			'mode' => fileperms($path),
			'owner' => fileowner($path),
			'group' => filegroup($path),
			'size' => filesize($path),
			'ctime' => filectime($path),
			'mtime' => filemtime($path),
			'atime' => fileatime($path),
			'dev' => $stat['dev'],
			'ino' => $stat['ino'],
			'uid' => $stat['uid'],
			'gid' => $stat['gid'],
		);
		
		return $info;
	}

}
