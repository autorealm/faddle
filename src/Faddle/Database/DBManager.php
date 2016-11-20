<?php namespace Faddle\Database;

/**
 * Database manager class
 *
 * This class implements methods for managing data objects, database rows etc. One 
 * of its features is automatic caching of loaded data.
 */
abstract class DBManager {

	private $table_name; //数据库对于表名称
	private $cache = array(); //缓存的数据
	private $item_class = ''; //对象类
	private $caching = true; //是否存在缓存

	/**
	 * Construct
	 * @return DataManager
	 */
	function __construct($item_class, $table_name, $caching = true) {
		$this->setItemClass($item_class);
		$this->setTableName($table_name);
		$this->setCaching($caching);
	}

	// ----------------------------------------------------
	//  Caching
	// ----------------------------------------------------

	/**
	 * Clear the item cache
	 *
	 * @access public
	 * @param void
	 * @return void
	 */
	function clearCache() {
		$this->cache = array();
	}

	// ---------------------------------------------------
	//  Getters and setters
	// ---------------------------------------------------

	/**
	 * Get the value of item class
	 *
	 * @access public
	 * @param void
	 * @return string
	 */
	function getItemClass() {
		return $this->item_class;
	}

	/**
	 * Set value of item class. This function will set the value only when item class is
	 * defined, else it will return FALSE.
	 *
	 * @access public
	 * @param string $value New item class value
	 * @return null
	 */
	function setItemClass($value) {
		$this->item_class = trim($value);
	}

	function setTableName($value) {
		$this->table_name = trim($value);
	}

	function getCaching() {
		return (boolean)$this->caching;
	}

	function setCaching($value) {
		$this->caching = (boolean)$value;
	}

	function isReady() {
		return class_exists($this->item_class);
	}

	function getObjectTypeName() {
		return $this->getItemClass()->getObjectTypeName();
	}

	function __call($name, $args) {
		throw new \UndefinedMethodException('Call to undefined method DataManager::' .
			$name . '()', $name, $args);
	}

}
