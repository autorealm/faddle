<?php namespace Faddle\Support\Queue;

use PDO;
use Exception;

class SQ {
	const QUEUE_TABLE = 'queues';
	const MESSAGE_TABLE = 'messages';
	protected $pdo;
	
	public function __construct($db) {
		if ($db instanceof PDO) $this->pdo = $db;
		else $this->init(strval($db));
	}
	
	public function getPDO() {
		return $this->$pdo;
	}
	
	private function init($sqlite='queue.sqlite') {
		$create_table = self::getStructure('sqlite');
		$pdo = new PDO('sqlite:'.$sqlite);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$pdo->exec($create_table);
		$this->pdo = $pdo;
	}
	
	public function enqueue($name, Message $message) {
		$qid    = $this->getQueueId($name);
		$body   = base64_encode(serialize($message));
		$md5    = md5($body);
		
		$sql = 'INSERT INTO ' . self::MESSAGE_TABLE . '
			(queue_id, body, created, timeout, md5)
			VALUES
			(:queue_id, :body, :created, :timeout, :md5)
			';
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindParam(':queue_id', $qid, PDO::PARAM_INT);
		$stmt->bindParam(':body', $body, PDO::PARAM_STR);
		$stmt->bindParam(':md5', $md5, PDO::PARAM_STR);
		$stmt->bindValue(':created', time(), PDO::PARAM_INT);
		$stmt->bindValue(':timeout', 30, PDO::PARAM_INT);
		$stmt->execute();
		return true;
	}
	
	public function dequeue($name, $max=1) {
		if ($max < 0) $max = 1;
		$messages  = array();
		$microtime = microtime(true); // cache microtime
		$db        = $this->pdo;
		$qid       = $this->getQueueId($name);
		$timeout   = $this->getQueueTimeout($qid);
		
		try {
			$db->beginTransaction();
			
			$sql = "SELECT *
					FROM " . self::MESSAGE_TABLE . "
					WHERE queue_id = :queue_id
					AND (handle IS NULL OR timeout+" . $timeout . " < " . (int)$microtime .")
					LIMIT ".$max;
			$stmt = $db->prepare($sql);
			$stmt->execute(array('queue_id' => $qid));
			
			foreach ($stmt->fetchAll() as $data) {
				$data['handle'] = md5(uniqid(rand(), true));
				
				$sql = "UPDATE " . self::MESSAGE_TABLE . "
						SET
							handle = :handle,
							timeout = :timeout
						WHERE
							message_id = :id
							AND (handle IS NULL OR timeout+" . $timeout . " < " . (int)$microtime.")";
				
				$stmt = $db->prepare($sql);
				$stmt->bindParam(':handle', $data['handle'], PDO::PARAM_STR);
				$stmt->bindParam(':id', $data['message_id'], PDO::PARAM_STR);
				$stmt->bindValue(':timeout', $microtime);
				$updated = $stmt->execute();
				
				if ($updated) {
					$messages[] = $data;
				}
			}
			$db->commit();
		} catch (Exception $e) {
			$db->rollBack();
			throw $e;
		}
		
		$m = array();
		foreach($messages as $msg) {
			$message = unserialize(base64_decode($msg['body']));
			if($message instanceof Message) {
				$message->message_id = $msg['message_id'];
				$m[] = $message;
			}
		}
		
		return new \ArrayObject($m);
	}
	
	public function execute(Message $message) {
		$result = null;
		try {
			$result = $message->execute();
			$this->remove($message);
		} catch(\Exception $e) {
			$this->log($message, $e);
		}
		return $result;
	}
	
	public function create($name, $timeout=9000) {
		try {
			$sql = 'INSERT INTO ' . self::QUEUE_TABLE . '
				(queue_name, timeout) VALUES (:queue_name, :timeout)';
			$stmt = $this->pdo->prepare($sql);
			$stmt->bindParam(':queue_name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':timeout', $timeout, PDO::PARAM_INT);
			$stmt->execute();
		} catch(\Exception $e) {}
		return true;
	}
	
	public function query($name) {
		$qid = $this->getQueueId($name);
		$list = array();
		$sql = 'SELECT * FROM ' . self::MESSAGE_TABLE . ' WHERE queue_id = :queue_id';
		$sth = $this->pdo->prepare($sql);
		$sth->execute(array('queue_id' => $qid));
		foreach($sth->fetchAll() as $msg) {
			$o = unserialize(base64_decode($msg['body']));
			$list[] = array(
				'queue_name'    => $name,
				'message_id'    => $msg['message_id'],
				'message_class' => get_class($o),
				'message'       => $o,
				'handle'        => $msg['handle'],
				'log'           => $msg['log'],
				'created'       => $msg['created'],
				'params'        => $o->toArray(),
				'timeout'       => $msg['timeout'],
			);
		}
		return $list;
	}
	
	public function getQueues() {
		$sql = 'SELECT * FROM ' . self::QUEUE_TABLE . ' WHERE 1';
		$sth = $this->pdo->prepare($sql);
		$sth->execute();
		$list = array();
		foreach($sth->fetchAll() as $q) {
			$list[] = array($q['queue_name'], $q['timeout']);
		}
		return new \ArrayObject($list);
	}
	
	public function getMessages($stuck=true) {
		if ($stuck) $sql = 'SELECT * FROM ' . self::MESSAGE_TABLE . ' WHERE handle IS NULL';
		else $sql = 'SELECT * FROM ' . self::MESSAGE_TABLE . ' WHERE handle IS NOT NULL';
		$sth = $this->pdo->prepare($sql);
		$sth->execute();
		$list = array();
		foreach($sth->fetchAll() as $msg) {
			$list[] = unserialize(base64_decode($msg['body']));
		}
		return new \ArrayObject($list);
	}
	
	protected function getQueueId($name) {
		$sql = 'SELECT queue_id FROM ' . self::QUEUE_TABLE . ' WHERE queue_name = ? LIMIT 1';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute(array($name));
		$id = $stmt->fetchColumn();
		if ($id !== false) return $id;
		else throw new Exception(sprintf('Queue "%s" not exists'.PHP_EOL, $name));
	}
	
	protected function getQueueTimeout($id) {
		$sql = 'SELECT timeout FROM ' . self::QUEUE_TABLE . ' WHERE queue_id = ? LIMIT 1';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute(array($id));
		$timeout = $stmt->fetchColumn();
		if ($timeout !== false) return $timeout;
		else throw new Exception(sprintf('Queue ID:%s not exists'.PHP_EOL, strval($id)));
	}
	
	public function clear($name) {
		if (isset($name)) {
			$qid = $this->getQueueId($name);
			$sth = $this->pdo->prepare('DELETE FROM ' . self::MESSAGE_TABLE . ' WHERE queue_id = ?');
			$sth->execute(array($qid));
			$sth = $this->pdo->prepare('DELETE FROM ' . self::QUEUE_TABLE . ' WHERE queue_id = ?');
			$sth->execute(array($qid));
		} else {
			$sth = $this->pdo()->prepare('DELETE FROM ' . self::MESSAGE_TABLE . ' WHERE 1');
			$sth->execute();
			$sth = $this->pdo()->prepare('DELETE FROM ' . self::QUEUE_TABLE . ' WHERE 1');
			$sth->execute();
		}
	}
	
	public function remove($message) {
		if (is_object($message)) $id = intval($message->message_id);
		else $id = intval($message);
		$sql = 'DELETE FROM ' . self::MESSAGE_TABLE . ' WHERE message_id = ? LIMIT 1';
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute(array($id));
		return true;
	}
	
	public function log(Message $message, Exception $err) {
		$sql = 'UPDATE ' . self::MESSAGE_TABLE . ' SET log=:log WHERE message_id=:id';
		$stmt = $this->pdo->prepare($sql);
		$stmt->bindValue(':log', $err->getMessage(), PDO::PARAM_STR);
		$stmt->bindValue(':id', $message->message_id, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function total($name) {
		if (isset($name)) {
			$qid = $this->getQueueId($name);
			$sql = 'SELECT COUNT(message_id) FROM ' . self::MESSAGE_TABLE . ' WHERE queue_id=' . $qid;
		} else {
			$sql = 'SELECT COUNT(message_id) FROM ' . self::MESSAGE_TABLE . ' WHERE 1';
		}
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute();
		return $stmt->fetchColumn();
	}
	
	public static function getStructure($type='sqlite') {
		$type = strtolower($type);
		$queue_table = self::QUEUE_TABLE ?: 'queues';
		$message_table = self::MESSAGE_TABLE ?: 'messages';
		$create_mysql_table = <<<EOF
CREATE TABLE IF NOT EXISTS `{$message_table}` (
	`message_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`queue_id` int(11) UNSIGNED NOT NULL,
	`handle` char(32) NOT NULL DEFAULT '',
	`body` text NOT NULL,
	`md5` char(32) NOT NULL DEFAULT '',
	`timeout` double NOT NULL,
	`created` int(11) UNSIGNED NOT NULL,
	`log` text NOT NULL,
	PRIMARY KEY (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{$queue_table}` (
	`queue_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`queue_name` varchar(100) NOT NULL DEFAULT '',
	`timeout` int(10) UNSIGNED NOT NULL DEFAULT 30,
	PRIMARY KEY (`queue_id`),
	UNIQUE KEY (`queue_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOF;
		$create_sqlite_table = <<<EOF
BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS `{$queue_table}` (
	queue_id INTEGER PRIMARY KEY AUTOINCREMENT,
	queue_name VARCHAR(100) NOT NULL UNIQUE,
	timeout INTEGER NOT NULL DEFAULT 30
);
CREATE TABLE IF NOT EXISTS `{$message_table}` (
	message_id INTEGER PRIMARY KEY AUTOINCREMENT,
	queue_id INTEGER,
	handle CHAR(32),
	`body` VARCHAR(8192) NOT NULL,
	md5 CHAR(32) NOT NULL,
	timeout REAL,
	created INTEGER,
	`log` TEXT
);
COMMIT;
EOF;
		if ($type == 'sqlite') return $create_sqlite_table;
		else return $create_mysql_table;
	}
	
}

abstract class Message {

	protected $_data = array();

	public function __construct(array $data = array()) {
		$this->_data = $data;
	}

	abstract public function execute();

	public function toArray() {
		return $this->_data;
	}

	public function __get($key) {
		if (!array_key_exists($key, $this->_data)) {
			return null;
		}
		return $this->_data[$key];
	}

	public function __set($key, $value) {
		$this->_data[$key] = $value;
	}

	public function __isset($key) {
		return array_key_exists($key, $this->_data);
	}

	public function __unset($key) {
		if (array_key_exists($key, $this->_data)) unset($this->_data[$key]);
	}

	public function __sleep() {
		return array('_data');
	}

	public function __toString() {
		return sprintf('Message %s with params %s'.PHP_EOL, get_class($this), print_r($this->_data, true));
	}

}
