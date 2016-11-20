<?php namespace Faddle\Storage;


class EasyCSV {

	protected $handle;
	protected $delimiter = ',';
	protected $enclosure = '"';

	/**
	 * @var bool
	 */
	private $headersInFirstRow = true;

	/**
	 * @var array|bool
	 */
	private $headers = false;

	/**
	 * @var
	 */
	private $init;

	/**
	 * @var bool|int
	 */
	private $headerLine = false;

	/**
	 * @var bool|int
	 */
	private $lastLine = false;

	/**
	 * EasyCSV 构造函数
	 * @param $path 文件路径
	 * @param string $mode 读写模式
	 * @param bool   $headersInFirstRow 是否第一行带信息头
	 */
	public function __construct($path, $mode = 'r+', $headersInFirstRow = true) {
		if (! file_exists($path)) {
			touch($path);
		}
		$this->handle = new \SplFileObject($path, $mode);
		$this->handle->setFlags(\SplFileObject::DROP_NEW_LINE);
		$this->headersInFirstRow = $headersInFirstRow;
	}

	public function __destruct() {
		$this->handle = null;
	}

	public function setDelimiter($delimiter) {
		$this->delimiter = $delimiter;
	}

	public function setEnclosure($enclosure) {
		$this->enclosure = $enclosure;
	}

	public function writeRow($row) {
		if (is_string($row)) {
			$row = explode(',', $row);
			$row = array_map('trim', $row);
		}
		return $this->handle->fputcsv($row, $this->delimiter, $this->enclosure);
	}

	public function writeFromArray(array $array) {
		foreach ($array as $key => $value) {
			$this->writeRow($value);
		}
	}

	/**
	 * @return bool
	 */
	public function getHeaders() {
		$this->init();
		return $this->headers;
	}

	/**
	 * @return array|bool
	 */
	public function getRow() {
		$this->init();
		if ($this->isEof()) {
			return false;
		}
		$row = $this->getCurrentRow();
		$isEmpty = $this->rowIsEmpty($row);
		if ($this->isEof() === false) {
			$this->handle->next();
		}
		if ($isEmpty === false) {
			return ($this->headers && is_array($this->headers)) ? array_combine($this->headers, $row) : $row;
		} elseif ($isEmpty) {
			// empty row, transparently try the next row
			return $this->getRow();
		} else {
			return false;
		}
	}

	/**
	 * @return bool
	 */
	public function isEof() {
		return $this->handle->eof();
	}

	/**
	 * @return array
	 */
	public function getAll() {
		$data = array();
		while ($row = $this->getRow()) {
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * @return int zero-based index
	 */
	public function getLineNumber() {
		return $this->handle->key();
	}

	/**
	 * @return int zero-based index
	 */
	public function getLastLineNumber() {
		if ($this->lastLine !== false) {
			return $this->lastLine;
		}
		$this->handle->seek($this->handle->getSize());
		$lastLine = $this->handle->key();
		$this->handle->rewind();
		
		return $this->lastLine = $lastLine;
	}

	/**
	 * @return array
	 */
	public function getCurrentRow() {
		return str_getcsv($this->handle->current(), $this->delimiter, $this->enclosure);
	}

	/**
	 * @param $lineNumber zero-based index
	 */
	public function advanceTo($lineNumber) {
		if ($this->headerLine > $lineNumber) {
			throw new \LogicException("Line Number $lineNumber is before the header line that was set");
		} elseif ($this->headerLine === $lineNumber) {
			throw new \LogicException("Line Number $lineNumber is equal to the header line that was set");
		}

		if ($lineNumber > 0) {
			$this->handle->seek($lineNumber - 1);
		} // check the line before

		if ($this->isEof()) {
			throw new \LogicException("Line Number $lineNumber is past the end of the file");
		}

		$this->handle->seek($lineNumber);
	}

	/**
	 * @param $lineNumber zero-based index
	 */
	public function setHeaderLine($lineNumber) {
		if ($lineNumber !== 0) {
			$this->headersInFirstRow = false;
		} else {
			return false;
		}

		$this->headerLine = $lineNumber;

		$this->handle->seek($lineNumber);

		// get headers
		$this->headers = $this->getRow();
	}

	protected function init() {
		if (true === $this->init) {
			return;
		}
		$this->init = true;
		
		if ($this->headersInFirstRow === true) {
			$this->handle->rewind();
			$this->headerLine = 0;
			$this->headers = $this->getRow();
		}
	}

	/**
	 * @param $row
	 * @return bool
	 */
	protected function rowIsEmpty($row) {
		$emptyRow = ($row === array(null));
		$emptyRowWithDelimiters = (array_filter($row) === array());
		$isEmpty = false;
		
		if ($emptyRow) {
			$isEmpty = true;
			return $isEmpty;
		} elseif ($emptyRowWithDelimiters) {
			$isEmpty = true;
			return $isEmpty;
		}
		
		return $isEmpty;
	}

}
