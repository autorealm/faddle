<?php namespace Faddle\Storage;

/**
 * SimpleCache
 * 
 */
class SimpleCache {

	static $_instance;
	static $_dbs;
	public static $db_file='caching.sqlite';

	protected function __construct() {
		
	}

	public static function getSqliteDb($db_file=null) {
		if (isset($db_file)) static::$db_file = $db_file;
		$db_file = static::$db_file;
		if (isset(static::$_dbs[$db_file])) return static::$_dbs[$db_file];
		if (! file_exists($db_file)) {
			if(! is_dir(dirname($db_file))) @mkdir(dirname($db_file), 0775);
			if (! ($db = new \SQLite3($db_file))) return false;
			$db->exec("PRAGMA synchronous=OFF;PRAGMA journal_mode=MEMORY;");
			$db->exec(
				"CREATE TABLE entity_caches(id INTEGER PRIMARY KEY, time INTEGER, " .
				"key VARCHAR not null unique, data BLOB not null, tags TEXT, " .
				"size INTEGER default 0, expire INTEGER default 0);"
			);
			$db->exec("CREATE INDEX by_key ON entity_caches(key);");
			$db->exec("CREATE INDEX by_tag ON entity_caches(tags);");
			$db->close();
		}
		if (! ($db = new \SQLite3($db_file))) return false;
		//$pdo = new \PDO('sqlite:'.$db_file);
		//$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		static::$_dbs[$db_file] = $db;
		
		return $db;
	}

	public static function getInstance($db_file=null) {
		if (isset($db_file)) static::$db_file = $db_file;
		if (!isset(static::$_instance)) {
			static::$_instance = new self();
		}
		if (!static::getSqliteDb())
			throw new \Exception('cannot connect the sqlite database: ' . static::$db_file);
		
		return static::$_instance;
	}

	public function set($key, $data, $expire=0, $tags=null) {
		$sqlite = static::getSqliteDb();
		try {
			$key = $sqlite->escapeString((string)$key);
			$data = $this->_pack($data);
			$size = strlen($data);
			$tags = strval($tags);
			$expire = intval($expire);
			$now = time();
			$stmt = $sqlite->prepare(
				"INSERT OR REPLACE INTO entity_caches(key, data, tags, size, expire, time) " .
				"VALUES (?, ?, ?, ?, ?, ?)"
				);
			$stmt->bindParam(1, $key, SQLITE3_TEXT);
			$stmt->bindParam(2, $data, SQLITE3_BLOB);
			$stmt->bindParam(3, $tags, SQLITE3_TEXT);
			$stmt->bindParam(4, $size, SQLITE3_INTEGER);
			$stmt->bindParam(5, $expire, SQLITE3_INTEGER);
			$stmt->bindParam(6, $now, SQLITE3_INTEGER);
			$query = $stmt->execute();
			return $query ? true : false;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function get($key) {
		$sqlite = static::getSqliteDb();
		try {
			$key = $sqlite->escapeString((string)$key);
			$data = $sqlite->querySingle("SELECT * FROM entity_caches WHERE key='" . $key . "'", true);
			if (! $data) return false;
			if ($this->_isExpired($data)) {
				$this->remove($key);
				return false;
			}
			return $this->_unpack($data['data']);
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function search($tag='') {
		$sqlite = static::getSqliteDb();
		try {
			$tag = $sqlite->escapeString((string)$tag);
			$query = $sqlite->query('SELECT * FROM entity_caches WHERE tags LIKE \'%'.$tag.'%\'');
			if (! $query or ! $_list = $query->fetchArray()) return false;
			$list = array();
			foreach ($_list as $data) {
				if ($this->_isExpired($data)) continue;
				$data['data'] = $this->_unpack($data['data']);
				$list[] = $data;
			}
			return $list;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getInfo($key) {
		$sqlite = static::getSqliteDb();
		try {
			$key = $sqlite->escapeString((string)$key);
			$query = $sqlite->query("SELECT * FROM entity_caches WHERE key='" . $key . "'");
			if (!$query || ! $data = $query->fetchArray())
				return false;
			$data['data'] = $this->_unpack($data['data']);
			return $data;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function getItemCount() {
		$sqlite = static::getSqliteDb();
		try {
			return $sqlite->querySingle("SELECT count(*) FROM entity_caches");
		} catch ( \Exception $e ) {
			return false;
		}
	}

	public function remove($key) {
		$sqlite = static::getSqliteDb();
		try {
			$key = $sqlite->escapeString((string)$key);
			$query = $sqlite->exec("DELETE FROM entity_caches WHERE key = '" . $key . "'");
			return $query ? true : false;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function clean() {
		$sqlite = static::getSqliteDb();
		try {
			$query = $sqlite->exec("DELETE FROM entity_caches");
			return $query ? true : false;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function flush() {
		$sqlite = static::getSqliteDb();
		try {
			$query = $sqlite->exec("DELETE FROM entity_caches WHERE expire > 0 AND (time + expire) < '" . time() . "'");
			return $query ? true : false;
		} catch (\Exception $e) {
			return false;
		}
	}

	private function _isExpired($data) {
		if ($data['expire'] === 0) return false;
		if (time() > (intval($data['time']) + $data['expire']))
			return true;
		else
			return false;
	}

	private function _pack($value) {
		try {
			return serialize($value);
		} catch (\Exception $e) {
			return $value;
		}
	}

	private function _unpack($value) {
		try {
			return unserialize($value);
		} catch (\Exception $e) {
			return $value;
		}
	}

}
