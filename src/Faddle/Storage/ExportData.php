<?php namespace Faddle\Storage;

/**
 * 导出数据存储类
 */
class ExportData {

	const DATA_TYPE_TEXT = 0; //文件类型: Text
	const DATA_TYPE_JSON = 1; //文件类型: JSON
	const DATA_TYPE_XML = 2; //文件类型: XML
	const DATA_TYPE_CSV = 3; //文件类型: CSV
	const DATA_TYPE_TSV = 4; //文件类型: TSV
	const DATA_TYPE_EXCEL_XML = 5; //文件类型: Excel-XML
	
	protected $exportTo; //导出到 'browser', 'file', 'string'
	protected $stringData; //字符串数据
	protected $tempFile; //临时文件句柄
	protected $tempFilename; //临时文件名称

	public $filename; //文件路径或名称
	public $filetype; //导出数据类型
	
	public $encoding = 'UTF-8'; // encoding type to specify in file.
	public $title = 'Sheet1'; // title for Worksheet or XML root.
	
	public function __construct($exportTo="browser", $filename="exportdata", $filetype=0) {
		$exportTo = strtolower($exportTo);
		if(!in_array($exportTo, array('browser','file','string') )) {
			throw new Exception("$exportTo is not a valid ExportData export type");
		}
		$this->exportTo = $exportTo;
		$this->filename = $filename;
		$this->filetype = intval($filetype);
	}
	
	public function initialize() {
		
		switch($this->exportTo) {
			case 'browser':
				$this->sendHttpHeaders();
				break;
			case 'string':
				$this->stringData = '';
				break;
			case 'file':
				$this->tempFilename = tempnam(sys_get_temp_dir(), 'exportdata');
				$this->tempFile = fopen($this->tempFilename, "w");
				break;
		}
		
		$this->write($this->generateHeader());
	}
	
	public function addRow($row) {
		$this->write($this->generateRow($row));
	}
	
	public function finalize() {
		
		$this->write($this->generateFooter());
		
		switch($this->exportTo) {
			case 'browser':
				flush();
				break;
			case 'string':
				// do nothing
				break;
			case 'file':
				// close temp file and move it to correct location
				fclose($this->tempFile);
				rename($this->tempFilename, $this->filename);
				break;
		}
	}
	
	public function getString() {
		return $this->stringData;
	}
	
	protected function write($data) {
		switch($this->exportTo) {
			case 'browser':
				echo $data;
				break;
			case 'string':
				$this->stringData .= $data;
				break;
			case 'file':
				fwrite($this->tempFile, $data);
				break;
		}
	}
	
	public function sendHttpHeaders() {
		switch ($this->filetype) {
		case (static::DATA_TYPE_TEXT):
			header("Content-type: text/text");
			header("Content-Disposition: attachment; filename=".basename($this->filename));
			break;
		case (static::DATA_TYPE_JSON):
			header("Content-type: text/json");
			header("Content-Disposition: attachment; filename=".basename($this->filename));
			break;
		case (static::DATA_TYPE_XML):
			header("Content-type: text/xml");
			header("Content-Disposition: attachment; filename=".basename($this->filename));
			break;
		case (static::DATA_TYPE_CSV):
			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=".basename($this->filename));
			break;
		case (static::DATA_TYPE_TSV):
			header("Content-type: text/tab-separated-values");
			header("Content-Disposition: attachment; filename=".basename($this->filename));
			break;
		case (static::DATA_TYPE_EXCEL_XML):
			header("Content-Type: application/vnd.ms-excel; charset=" . $this->encoding);
			header("Content-Disposition: inline; filename=\"" . basename($this->filename) . "\"");
			break;
		}
		
	}
	
	protected function generateHeader() {
		$output = '';
		$excelHeader = "<?xml version=\"1.0\" encoding=\"%s\"?\>\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\" "
				."xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\" "
				."xmlns:html=\"http://www.w3.org/TR/REC-html40\">";
		
		if ($this->filetype == static::DATA_TYPE_XML) {
			$output = "<?xml version='1.0' encoding='UTF_8'?>\n";
			$output .= "<" . htmlentities($this->title) . ">\n";
			
		} elseif ($this->filetype == static::DATA_TYPE_EXCEL_XML) {
			// workbook header
			$output = stripslashes(sprintf($excelHeader, $this->encoding)) . "\n";
			// Set up styles
			$output .= "<Styles>\n";
			$output .= "<Style ss:ID=\"sDT\"><NumberFormat ss:Format=\"Short Date\"/></Style>\n";
			$output .= "</Styles>\n";
			// worksheet header
			$output .= sprintf("<Worksheet ss:Name=\"%s\">\n    <Table>\n", htmlentities($this->title));
			
		} elseif ($this->filetype == static::DATA_TYPE_JSON) {
			$output = "{\n";
		}
		return $output;
	}
	
	protected function generateFooter() {
		$output = '';
		if ($this->filetype == static::DATA_TYPE_XML) {
			// xml footer
			$output .= '</' . htmlentities($this->title) . '>';
		} elseif ($this->filetype == static::DATA_TYPE_EXCEL_XML) {
			// workbook footer
			$output .= "    </Table>\n</Worksheet>\n";
			$output .= "</Workbook>";
		} elseif ($this->filetype == static::DATA_TYPE_JSON) {
			$output = '}';
		}
		return $output;
	}
	
	protected function generateRow($row) {
		switch ($this->filetype) {
		case (static::DATA_TYPE_TEXT):
			$output = serialize($row);
			return $output . "\n";
		case (static::DATA_TYPE_JSON):
			$output = array();
			foreach ($row as $key => $value) {
				$key = '"'. $key .'"';
				if (is_array($value)) {
					$value = json_encode($value);
				}
				$value = '"'. str_replace('"', '\"', $value) .'"';
				$output[] = $key . ':' . $value;
			}
			return "\t" . implode(",", $row) . "\n";
		case (static::DATA_TYPE_XML):
			
			return $this->xml_encode($row);
		case (static::DATA_TYPE_CSV):
			foreach ($row as $key => $value) {
				// Escape inner quotes and wrap all contents in new quotes.
				// Note that we are using \" to escape double quote not ""
				$row[$key] = '"'. str_replace('"', '\"', $value) .'"';
			}
			return implode(",", $row) . "\n";
		case (static::DATA_TYPE_TSV):
			foreach ($row as $key => $value) {
				// Escape inner quotes and wrap all contents in new quotes.
				// Note that we are using \" to escape double quote not ""
				$row[$key] = '"'. str_replace('"', '\"', $value) .'"';
			}
			return implode("\t", $row) . "\n";
		case (static::DATA_TYPE_EXCEL_XML):
			$output = '';
			$output .= "        <Row>\n";
			foreach ($row as $k => $v) {
				$output .= $this->generateCell($v);
			}
			$output .= "        </Row>\n";
			return $output;
		}
		
	}
	
	/**
	 * Excel-XML 创建一格数据方法
	 */
	private function generateCell($item) {
		$output = '';
		$style = '';
		
		// Tell Excel to treat as a number. Note that Excel only stores roughly 15 digits, so keep 
		// as text if number is longer than that.
		if(preg_match("/^-?\d+(?:[.,]\d+)?$/",$item) && (strlen($item) < 15)) {
			$type = 'Number';
		}
		// Sniff for valid dates; should look something like 2010-07-14 or 7/14/2010 etc. Can
		// also have an optional time after the date.
		//
		// Note we want to be very strict in what we consider a date. There is the possibility
		// of really screwing up the data if we try to reformat a string that was not actually 
		// intended to represent a date.
		elseif(preg_match("/^(\d{1,2}|\d{4})[\/\-]\d{1,2}[\/\-](\d{1,2}|\d{4})([^\d].+)?$/",$item) &&
					($timestamp = strtotime($item)) &&
					($timestamp > 0) &&
					($timestamp < strtotime('+500 years'))) {
			$type = 'DateTime';
			$item = strftime("%Y-%m-%dT%H:%M:%S",$timestamp);
			$style = 'sDT'; // defined in header; tells excel to format date for display
		}
		else {
			$type = 'String';
		}
				
		$item = str_replace('&#039;', '&apos;', htmlspecialchars($item, ENT_QUOTES));
		$output .= "            ";
		$output .= $style ? "<Cell ss:StyleID=\"$style\">" : "<Cell>";
		$output .= sprintf("<Data ss:Type=\"%s\">%s</Data>", $type, $item);
		$output .= "</Cell>\n";
		
		return $output;
	}
	
	/**
	 * 编码 XML 方法
	 */
	private function xml_encode($data) {
		$xml = "";
		$attr = "";
		foreach($data as $key => $value) {
			//处理xml不识别数字节点
			if(is_numeric($key)) {
				$attr = " id='{$key}'";
				$key = "item";
			}
			$xml .= "<{$key}{$attr}>";
			$xml .= is_array($value) ? "\n".$this->xml_encode($value) : $value; //递推处理
			$xml .= "</{$key}>\n";
		}
		return $xml;
	}

}

