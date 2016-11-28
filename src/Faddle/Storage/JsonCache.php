<?php namespace Faddle\Storage;

use PDO;
use Exception;

$table_json_entity = "

CREATE TABLE IF NOT EXISTS `json_entity` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(180) NOT NULL COMMENT '键名',
  `data` mediumtext NOT NULL COMMENT '存储的数据',
  `size` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '数据大小',
  `expires` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '过期时间',
  `ctime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '创建/更新时间',
  `tags` varchar(210) DEFAULT NULL COMMENT '标签',
  `db` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '数据库号',
  `extra` text DEFAULT NULL COMMENT '扩展数据',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_name` (`name`,`db`),
  KEY `by_tag` (`tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='JSON 数据对象存储表';

";

class JsonCache {
    
    private $pdo;
    private $table = 'json_entity';
    private $db = 0;
    
    public function __construct($pdo, $db=0) {
        
        $this->pdo = $pdo;
        $this->db = intval($db);
    }
    
    public static function createTable($pdo) {
        $pdo->exec($table_json_entity);
        
    }
    
    public function has($name) {
        $statement = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE name=:name AND db={$this->db}");
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        if ($statement->execute()) {
            return (bool) $statement->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
    
    public function remove($name) {
        $statement = $this->pdo->prepare("DELETE FROM {$this->table} WHERE name=:name AND db={$this->db}");
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        return $statement->execute();
    }
    
    public function with($db) {
        $this->db = intval($db);
        return $this;
    }
    
    public function set($name, $data, $expires=0, $tags=null) {
        
        $statement = $this->pdo->prepare("INSERT OR REPLACE INTO {$this->table} (name, data, size, expires, tags, db) 
            VALUES(:name, :data, :size, :expires, :tags, :db)");
        $data = $this->_pack($data);
        $size = strlen($data);
        $tags = is_null($tags) ? null : strval($tags);
        $expires = intval($expires);
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        $statement->bindParam(':data', $data, PDO::PARAM_STR);
        $statement->bindParam(':size', $size, PDO::PARAM_INT);
        $statement->bindParam(':tags', $tags, is_null($tags) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindParam(':expires', $expires, PDO::PARAM_INT);
        $statement->bindParam(':db', $this->db, PDO::PARAM_INT);
        return $statement->execute() ? compact('name', 'data', 'size', 'tags') : false;
    }
    
    public function get($name) {
        if ($result = $this->getInfo($name)) {
            return $result['data'];
        } else {
            return false;
        }
    }
    
    public function getInfo($name) {
        $statement = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE name=:name AND db={$this->db}");
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        //$statement->bindColumn('ctime', $time);
        if ($statement->execute()) {
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ($this->_isExpired($result)) {
                //$this->remove($name);
                return false;
            } else {
                $result['data'] = $this->_unpack($result['data']);
                return $result;
            }
        }
        return false;
    }
    
    public function search($tags) {
        $value = '%' . trim(strval($tags)) . '%';
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE tags LIKE ? AND db={$this->db}");
        $stmt->bindParam(1, $value, PDO::PARAM_STR);
        if ($stmt->execute()) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $idx => &$ret) {
                if ($this->_isExpired($ret)) unset($result[$idx]);
                $ret['data'] = $this->_unpack($ret['data']);
                
            }
            return $result;
        }
        return false;
    }
    
    public function update($name, array $params) {
        $sql = "UPDATE {$this->table} SET ";
        
        $updates = array();
        foreach ($params as $column=>$value) {
            $updates[] = "{$column}=:{$column}";
        }
        
        $sql .= implode(', ', $updates);
        $sql .= " WHERE name=:name AND db={$this->db}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        foreach ($params as $column=>$value) {
            $stmt->bindParam(":{$column}", $value, $this->get_pdo_param_type($value));
        }
        
        return $stmt->execute();
    }
    
    public function flush() {
        $statement = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires>0 AND
            (ctime+expires)<:time AND db={$this->db}");
        $statement->bindParam(':time', time(), PDO::PARAM_INT);
        return $statement->execute();
    }
    
    private function _isExpired($result) {
        if ($result['expires'] === 0) return false;
        if (time() > (intval($result['ctime']) + $result['expires']))
            return true;
        else
            return false;
    }
    
    private function _pack($data) {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        try {
            return serialize($data);
        } catch (\Exception $e) {
            return $data;
        }
    }
    
    private function _unpack($data) {
        try {
            $data = unserialize($data);
        } catch (\Exception $e) {}
        try {
            return json_decode($data, true);
        } catch (\Exception $e) {
            return $data;
        }
    }
    
    private function get_pdo_param_type($value) {
        $type = PDO::PARAM_STR;
        switch ($value) {
            case is_int($value):
                $type = PDO::PARAM_INT;
                break;
            case is_bool($value):
                $type = PDO::PARAM_BOOL;
                break;
            case is_null($value):
                $type = PDO::PARAM_NULL;
                break;
        }
        return $type;
    }
    
    private function build_search_keywords($kw, $field='tags') {
        $kw = preg_replace('/\s+/', ' ', $this->clean_xss(trim($kw)));
        if (empty($kw)) return '';
        $words = explode(' ', $kw);
        $_words = array();
        foreach ($words as $w) {
            $w = trim($w);
            //if ($w == '') continue;
            if (substr($w, 0, 1) == '-') {
                $w = substr($w, 1);
                $_words[] = " ($field NOT LIKE '%$w%')"; //空格 转换
            } else {
                $_words[] = "($field LIKE '%$w%')";
            }
            
        }
        $wd = implode(' OR ', $_words);
        $wd = str_replace(' OR  ', ' AND ', $wd);
        return $wd;
    }
    
    private function clean_xss($string, $checkinput=true) {
        $string = str_replace("\r\n", "\n", $string);
        $string = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $string);
        $string = htmlspecialchars($string);
        
        if (!$checkinput) return $string;
        
        // 去除斜杠
        if (get_magic_quotes_gpc()) {
            $string = stripslashes($string);
        }
        //$string = mysql_real_escape_string($string);
        // 如果不是数字则加引号
        if (!is_numeric($string)) {
            //$string = "'" . $string . "'";
        }
        
        return $string;
    }
    
}
