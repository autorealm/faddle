<?php namespace Faddle\Storage;

/**
 * JSON 数据存储类
 * @since 2015-10-21
 */
class JsonStorage {

	protected $jsonFile;
	protected $fileHandle;
	protected $fileData = array();
	
	/**
	 * 构造函数
	 * 
	 * @param string $jsonFile JSON 文件路径
	 * @param boolean $create 文件不存在时是否创建
	 */
	public function __construct($jsonFile, $create=false) {
		if (!file_exists($jsonFile)) {
			if($create === true) {
				$this->create($jsonFile, true);
			} else {
				throw new Exception("Json-File not found: ".$jsonFile);
			}
		}
		$this->jsonFile = $jsonFile;
		$this->fileData = json_decode(file_get_contents($this->jsonFile), true);
		$this->lock();
	}
	
	public function __destruct() {
		$this->save();
		fclose($this->fileHandle);
	}
	
	protected function create($path) {
		if(is_array($path)) $path = $path[0];
		if(file_exists($path))
			throw new Exception("Json-File already exists: ".$path);
		if(fclose(fopen($path, 'a'))) {
			return true;
		} else {
			throw new Exception("Json-File couldn't be created: ".$path);
		}
	}	
	
	protected function lock() {
		$handle = fopen($this->jsonFile, "w");
		if (flock($handle, LOCK_EX)) $this->fileHandle = $handle;
		else throw new Exception("Json-File Error: Can't set file-lock");
	}
	
	protected function save() {
		if (fwrite($this->fileHandle, json_encode($this->fileData))) return true;
		else throw new Exception("Json-File Error: Can't write data to: ".$this->jsonFile);
	}
	
	public function selectAll() {
		return $this->fileData;
	}
	
	public function select($key, $val = 0) {
		$result = array();
		if (is_array($key)) $result = $this->select($key[1], $key[2]);
		else {
			$data = $this->fileData;
			foreach($data as $_key => $_val) {
				if (isset($data[$_key][$key])) {
					if ($data[$_key][$key] == $val) {
						$result[] = $data[$_key];
					}
				}
			}
		}
		return $result;
	}
	
	public function updateAll($data = array()) {
		if (isset($data[0]) && substr_compare($data[0],$this->jsonFile,0)) $data = $data[1];
		return $this->fileData = array($data);
	}
	
	public function update($key, $val = 0, $newData = array()) {
		$result = false;
		if (is_array($key)) $result = $this->update($key[1], $key[2], $key[3]);
		else {
			$data = $this->fileData;
			foreach($data as $_key => $_val) {
				if (isset($data[$_key][$key])) {
					if ($data[$_key][$key] == $val) {
						$data[$_key] = $newData;
						$result = true;
						break;
					}
				}
			}
			if ($result) $this->fileData = $data;
		}
		return $result;
	}
	
	public function insert($data = array(), $create = false) {
		if (isset($data[0]) && substr_compare($data[0],$this->jsonFile,0)) $data = $data[1];
		$this->fileData[] = $data;
		return true;
	}
	
	public function deleteAll() {
		$this->fileData = array();
		return true;
	}
	
	public function delete($key, $val = 0) {
		$result = 0;
		if (is_array($key)) $result = $this->delete($key[1], $key[2]);
		else {
			$data = $this->fileData;
			foreach($data as $_key => $_val) {
				if (isset($data[$_key][$key])) {
					if ($data[$_key][$key] == $val) {
						unset($data[$_key]);
						$result++;
					}
				}
			}
			if ($result) {
				sort($data);
				$this->fileData = $data;
			}
		}
		return $result;
	}

}
