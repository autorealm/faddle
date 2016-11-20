<?php namespace Faddle\Database\Error;

/**
 * Query error
 *
 * @version 1.0
 */
class DBQueryError extends \Error {

	/**
	 * SQL that broke
	 *
	 * @var string
	 */
	private $sql;

	/**
	 * Error number
	 *
	 * @var integer
	 */
	private $error_number;

	/**
	 * Error message
	 *
	 * @var string
	 */
	private $error_message;

	/**
	 * Construct the DBQueryError
	 *
	 * @access public
	 * @param void
	 * @return DBQueryError
	 */
	function __construct($sql, $error_number, $error_message, $message = null) {
		if (is_null($message)) {
			$message = "Query failed with message '$error_message'";
		}
		parent::__construct($message);
		$this->setSQL($sql);
		$this->setErrorNumber($error_number);
		$this->setErrorMessage($error_message);

	}

	/**
	 * Return errors specific params...
	 *
	 * @access public
	 * @param void
	 * @return array
	 */
	function getAdditionalParams() {
		return array(
			'sql' => $this->getSQL(),
			'error number' => $this->getErrorNumber(),
			'error message' => $this->getErrorMessage()); // array
	}

	/**
	 * Get sql
	 *
	 * @access public
	 * @param null
	 * @return string
	 */
	function getSQL() {
		return $this->sql;
	}

	/**
	 * Set sql value
	 *
	 * @access public
	 * @param string $value
	 * @return null
	 */
	function setSQL($value) {
		$this->sql = $value;
	}

	/**
	 * Get error_number
	 *
	 * @access public
	 * @param null
	 * @return integer
	 */
	function getErrorNumber() {
		return $this->error_number;
	}

	/**
	 * Set error_number value
	 *
	 * @access public
	 * @param integer $value
	 * @return null
	 */
	function setErrorNumber($value) {
		$this->error_number = $value;
	}

	/**
	 * Get error_message
	 *
	 * @access public
	 * @param null
	 * @return string
	 */
	function getErrorMessage() {
		return $this->error_message;
	}

	/**
	 * Set error_message value
	 *
	 * @access public
	 * @param string $value
	 * @return null
	 */
	function setErrorMessage($value) {
		$this->error_message = $value;
	}

}
