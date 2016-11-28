<?php namespace Faddle\Common;

use PDO;
use PDOException;

/**
 * PDO ·â×°Àà
 */
class PdoWrapper {

	# @object, The PDO object
	private $pdo;

	# @object, PDO statement object
	private $query;

	# @array,  The database settings
	private $settings;

	# @bool ,  Connected to the database
	private $isConnected = false;

	# @string, file for logging exceptions
	private $logPath;

	# @array, The parameters of the SQL query
	private $parameters;

	/**
	 * Default Constructor 
	 */
	public function __construct($dsn, $user=null, $password=null) {
		if ($dsn instanceof PDO) {
			$this->pdo = $dsn;
			$this->isConnected = true;
		} else {
			$this->connect($dsn, $user, $password);
		}
		$this->parameters = array();
	}

	/**
	 * This method makes connection to the database.
	 */
	private function connect($dsn, $user='', $password='') {
		try {
			$this->pdo = new PDO($dsn, $user, $password,
				array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
			);
			# We can now log any exceptions on Fatal error. 
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			# Disable emulation of prepared statements, use REAL prepared statements instead.
			$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			# Connection succeeded, set the boolean to true.
			$this->isConnected = true;
		} catch (PDOException $e) {
			# Write into log
			$this->log($e->getMessage());
			throw $e;
		}
	}

	/**
	 * Close the PDO connection
	 */
	public function close() {
		# Set the PDO object to null to close the connection
		# http://www.php.net/manual/en/pdo.connections.php
		$this->pdo = null;
	}

	public function __call($method, $args) {
		if (method_exists($this->pdo, $method)) {
			return call_user_func_array(array($this->pdo, $method), $args);
		}
	}

	/**
	 * Every method which needs to execute a SQL query uses this method.
	 * 
	 * 1. If not connected, connect to the database.
	 * 2. Prepare Query.
	 * 3. Parameterize Query.
	 * 4. Execute Query.
	 * 5. On exception : Write Exception into the log + SQL query.
	 * 6. Reset the Parameters.
	 */
	private function init($query, $parameters='') {
		# Connect to database
		if (!$this->isConnected) {
			throw new \Exception('PDO not connected.');
		}
		try {
			# Prepare query
			$this->query = $this->pdo->prepare($query);
			# Add parameters to the parameter array 
			$this->bindMore($parameters);
			# Bind parameters
			if (!empty($this->parameters)) {
				foreach ($this->parameters as $param => $value) {
					$type = PDO::PARAM_STR;
					switch ($value[1]) {
						case is_int($value[1]):
							$type = PDO::PARAM_INT;
							break;
						case is_bool($value[1]):
							$type = PDO::PARAM_BOOL;
							break;
						case is_null($value[1]):
							$type = PDO::PARAM_NULL;
							break;
					}
					// Add type when binding the values to the column
					$this->query->bindValue($value[0], $value[1], $type);
				}
			}
			# Execute SQL 
			$this->query->execute();
		} catch (PDOException $e) {
			# Write into log and display Exception
			$this->log($e->getMessage(), $query);
			throw $e;
		}
		
		# Reset the parameters
		$this->parameters = array();
	}

	/**
	 * @void 
	 *
	 * Add the parameter to the parameter array
	 * @param string $para  
	 * @param string $value 
	 */
	public function bind($para, $value) {
		$this->parameters[sizeof($this->parameters)] = [":" . $para , $value];
		return $this;
	}

	/**
	 * @void
	 * 
	 * Add more parameters to the parameter array
	 * @param array $parray
	 */
	public function bindMore($parray) {
		if (empty($this->parameters) && is_array($parray)) {
			$columns = array_keys($parray);
			foreach ($columns as $i => &$column) {
				$this->bind($column, $parray[$column]);
			}
		}
		return $this;
	}

	/**
	 * If the SQL query  contains a SELECT or SHOW statement it returns an array containing all of the result set row
	 * If the SQL statement is a DELETE, INSERT, or UPDATE statement it returns the number of affected rows
	 *
	 *  @param  string $query
	 * @param  array  $params
	 * @param  int    $fetchmode
	 * @return mixed
	 */
	public function query($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
		$query = trim(str_replace("\r", " ", $query));
		$this->init($query, $params);
		$rawStatement = explode(" ", preg_replace("/\s+|\t+|\n+/", " ", $query));
		
		# Which SQL statement is used 
		$statement = strtolower($rawStatement[0]);
		if ($statement === 'select' || $statement === 'show') {
			return $this->query->fetchAll($fetchmode);
		} elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
			return $this->query->rowCount();
		} else {
			return NULL;
		}
	}

	/**
	 * Returns an array which represents a column from the result set 
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @return array
	 */
	public function column($query, $params = null) {
		$this->init($query, $params);
		$columns = $this->query->fetchAll(PDO::FETCH_NUM);
		$column = null;
		foreach ($columns as $cells) {
			$column[] = $cells[0];
		}
		
		return $column;
	}

	/**
	 * Returns an array which represents a row from the result set 
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @param  int    $fetchmode
	 * @return array
	 */
	public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC) {
		$this->init($query, $params);
		$result = $this->query->fetch($fetchmode);
		$this->query->closeCursor(); // Frees up the connection to the server so that other SQL statements may be issued,
		return $result;
	}

	/**
	 * Returns the value of one single field/column
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @return string
	 */
	public function single($query, $params = null) {
		$this->init($query, $params);
		$result = $this->query->fetchColumn();
		$this->query->closeCursor(); // Frees up the connection to the server so that other SQL statements may be issued
		return $result;
	}

	public function insert($table_name, array $bindings = array()) {
		if(!empty($bindings)) {
			$fields     =  array_keys($bindings);
			$fieldsvals =  array(implode(",", $fields), ":" . implode(",:", $fields));
			$sql = "INSERT INTO ".$table_name." (".$fieldsvals[0].") VALUES (".$fieldsvals[1].")";
		} else {
			$sql = "INSERT INTO ".$table_name." () VALUES ()";
		}
		return $this->query($sql, $bindings);
	}

	public function update($table_name, $pk_field = 'id', $pk_value = 0, array $bindings = array()) {
		$fieldsvals = '';
		if (array_key_exists($pk_field, $bindings))
			unset($bindings[$pk_field]);
		$columns = array_keys($bindings);
		foreach ($columns as $column) {
			$fieldsvals .= $column . " = :". $column . ",";
		}
		$fieldsvals = substr_replace($fieldsvals , '', -1);
		$bindings = array_merge($bindings, array($pk_field=>$pk_value));
		if (count($columns) > 1 ) {
			$sql = "UPDATE " . $table_name .  " SET " . $fieldsvals . " WHERE " . $pk_field . " = :" . $pk_field;
			return $this->query($sql, $bindings);
		}
		return null;
	}

	public function search($table_name, $bindings = array(), $sort = array()) {
		$sql = "SELECT * FROM " . $table_name;
		if (!empty($bindings)) {
			$fieldsvals = array();
			$columns = array_keys($bindings);
			foreach($columns as $column) {
				$fieldsvals [] = $column . " = :". $column;
			}
			$sql .= " WHERE " . implode(" AND ", $fieldsvals);
		}
		if (!empty($sort)) {
			$sortvals = array();
			foreach ($sort as $key => $value) {
				$sortvals[] = $key . " " . $value;
			}
			$sql .= " ORDER BY " . implode(", ", $sortvals);
		}
		return $this->query($sql, $bindings);
	}

	public function delete($table_name, $pk_field = 'id', $pk_value = 0) {
		$sql = "DELETE FROM " . $table_name . " WHERE " . $pk_field . " = :" . $pk_field . " LIMIT 1" ;
		return $this->query($sql, array($pk_field=>$pk_value));
	}

	public function find($table_name, $pk_field = 'id', $pk_value = 0) {
		$sql = "SELECT * FROM " . $table_name ." WHERE " . $pk_field . " = :" . $pk_field;    
		return $this->row($sql, array($pk_field=>$pk_value));
	}

	public function findOne($table_name, $bindings) {
		$fieldsvals = array();
		$columns = array_keys($bindings);
		foreach($columns as $column) {
			$fieldsvals [] = $column . " = :". $column;
		}
		$sql = "SELECT * FROM " . $table_name ." WHERE "  . implode(" AND ", $fieldsvals) . " LIMIT 1";    
		return $this->query($sql, $bindings);
	}

	public function getPDO() {
		return $this->pdo;
	}

	public function setLogPath($logPath) {
		$this->logPath = $logPath;
	}

	/**
	 * Writes the log and returns the exception
	 *
	 * @param  string $message
	 * @param  string $sql
	 * @return string
	 */
	private function log($message, $sql = '') {
		$exception = 'Unhandled Exception. <br />';
		$exception .= $message;
		$exception .= "<br /> You can find the error back in the log.";
		
		if (!empty($sql)) {
			# Add the Raw SQL to the Log
			$message .= "\r\nRaw SQL : " . $sql;
		}
		# Write into log
		if (is_string($this->logPath) and method_exists(Logger::class, 'write'))
			Logger::write($message, __CLASS__, $this->logPath);
		
		return $exception;
	}

}