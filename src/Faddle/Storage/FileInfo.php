<?php namespace Faddle\Storage;

/**
 * 文件信息类
 */
class FileInfo extends \SplFileInfo {

	protected $comment = '';
	protected $mimetype;

	public static function getInfo($path) {
		if (!file_exists($path)) {
			return false;
		}
		clearstatcache(false, $path);
		
		$stat = stat($path);
		$info = array(
			'path' => $path,
			'dir' => dirname($path),
			'name' =>  pathinfo($path, PATHINFO_FILENAME);,
			'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
			'is_dir' => is_dir($path),
			'is_file' => is_file($path),
			'is_readable' => is_readable($path),
			'is_writable' => is_writable($path),
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

	/**
	 * Get mimetype
	 *
	 * @return string
	 */
	public function getMimetype() {
		if (isset($this->mimetype) === false) {
			$finfo = new \finfo(FILEINFO_MIME);
			$mimetype = $finfo->file($this->getPathname());
			$mimetypeParts = preg_split('/\s*[;,]\s*/', $mimetype);
			$this->mimetype = strtolower($mimetypeParts[0]);
			unset($finfo);
		}
		
		return $this->mimetype;
	}

	/**
	 * Get md5
	 *
	 * @return string
	 */
	public function getMd5() {
		return md5_file($this->getPathname());
	}

	/**
	 * Get a specified hash
	 *
	 * @return string
	 */
	public function getHash($algorithm = 'md5') {
		return hash_file($algorithm, $this->getPathname());
	}

	/**
	 * Get image dimensions
	 *
	 * @return array formatted array of dimensions
	 */
	public function getDimensions() {
		list($width, $height) = getimagesize($this->getPathname());
		
		return array(
			'width' => $width,
			'height' => $height
		);
	}

	/**
	 * Is this file uploaded with a POST request?
	 *
	 * This is a separate method so that it can be stubbed in unit tests to avoid
	 * the hard dependency on the `is_uploaded_file` function.
	 *
	 * @return bool
	 */
	public function isUploadedFile() {
		return is_uploaded_file($this->getPathname());
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @param string $comment
	 */
	public function setComment($comment) {
		$this->comment = $comment;
	}

	/**
	 * Cleans up a path and removes relative parts, also strips leading slashes
	 *
	 * @param string $path
	 * @return string
	 */
	protected function cleanPath($path) {
		$path    = str_replace('\\', '/', $path);
		$path    = explode('/', $path);
		$newpath = array();
		foreach ($path as $p) {
			if ($p === '' || $p === '.') {
				continue;
			}
			if ($p === '..') {
				array_pop($newpath);
				continue;
			}
			array_push($newpath, $p);
		}
		return trim(implode('/', $newpath), '/');
	}

	/**
	 * Strip given prefix or number of path segments from the filename
	 *
	 * The $strip parameter allows you to strip a certain number of path components from the filenames
	 * found in the tar file, similar to the --strip-components feature of GNU tar. This is triggered when
	 * an integer is passed as $strip.
	 * Alternatively a fixed string prefix may be passed in $strip. If the filename matches this prefix,
	 * the prefix will be stripped. It is recommended to give prefixes with a trailing slash.
	 *
	 * @param  int|string $strip
	 * @return FileInfo
	 */
	public function strip($strip) {
		$filename = $this->getPath();
		$striplen = strlen($strip);
		if (is_int($strip)) {
			// if $strip is an integer we strip this many path components
			$parts = explode('/', $filename);
			if (!$this->getIsdir()) {
				$base = array_pop($parts); // keep filename itself
			} else {
				$base = '';
			}
			$filename = join('/', array_slice($parts, $strip));
			if ($base) {
				$filename .= "/$base";
			}
		} else {
			// if strip is a string, we strip a prefix here
			if (substr($filename, 0, $striplen) == $strip) {
				$filename = substr($filename, $striplen);
			}
		}

		$this->setPath($filename);
	}

	/**
	 * Does the file match the given include and exclude expressions?
	 *
	 * Exclude rules take precedence over include rules
	 *
	 * @param string $include Regular expression of files to include
	 * @param string $exclude Regular expression of files to exclude
	 * @return bool
	 */
	public function match($include = '', $exclude = '') {
		$extract = true;
		if ($include && !preg_match($include, $this->getPath())) {
			$extract = false;
		}
		if ($exclude && preg_match($exclude, $this->getPath())) {
			$extract = false;
		}
		
		return $extract;
	}

}
