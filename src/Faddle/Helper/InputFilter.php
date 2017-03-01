<?php namespace Faddle\Helper;

use Faddle\Helper\TextUtils as TextUtils;
use Faddle\Helper\Validator as Validator;

/**
 * 输入数据过滤器
 * @author KYO
 * @since 2016-11-23
 */
class InputFilter implements \StdClass {
    
    protected static $modifiers = array();
    public $errors = array();
    
    public function __construct() {
        
    }
    
    public function __call($filter, $data) {
        $args = func_get_args();
        //$filter = trim(array_shift($args));
        if (isset($this->modifiers[$filter])) {
               return call_user_func($this->modifiers[$filter], $data[0]);
        }
        throw new \Exception("Filter extension '$filter' does not exist.");
    }
    
    public static function make() {
        $instance = new self;
        return $instance;
    }
    
    public static function extend($filter, $callback) {
        if (is_array($filter)) {
            self::$modifiers = array_merge(self::$modifiers, $filter);
        } else {
            self::$modifiers[strtolower($filter)] = $callback;
        }
    }
    
    public function filter(&$data, $rules = []) {
        
        foreach ($rules as $key => $rule) {
            
            $value = $this->getValue($data, $key);
            if (is_callable($rule)) {
                return call_user_func($rule, $value);
            }
            $filters = $this->parseFilters($rule);
            $required = in_array('required', array_column($filters, 0));
            if ($required and !$value) {
                $this->errors[$key] = 'Not validated by require.';
                continue;
            }
            foreach ($filters as $filter) {
                list($filter, $params) = $filter;
                array_unshift($params, $value);
                try {
                    $value = call_user_func([$this, 'applyfilter'], $filter, $params);
                    if (!$required or ($required and $value and $value !== true)) {
                        $this->setValue($data, $key, $value);
                    } else if ($required and !$value) {
                        $this->errors[$key] = 'Not validated by filter: [' . $filter . '].';
                        break;
                    }
                } catch(\Exception $e) {
                    if ($required) {
                        $this->errors[$key] = $e->getMessage();
                        break;
                    } else {
                        continue;
                    }
                }
            }
        }
    }
    
    public function valid($data, $rules = []) {
        return $this->filter($data, $rules);
    }
    
    protected function parseFilters($filters) {
        $_filters = [];
        if (is_string($filters)) {
            $filters = explode('|', $filters);
        }
        foreach ((array) $filters as $key => $filter) {
            
            if (is_numeric($key)) {
                if (strpos($filter, ':')) {
                    list($filter, $params) = explode(':', $filter, 2);
                    $params = explode(',', $params);
                } else {
                    $params = [];
                }
            } else {
                $filter = $key;
                $params = is_array($filter) ? $filter : explode(',', $filter);
            }
            if (is_string($filter)) $filter = strtolower($filter);
            $_filters[] = [$filter, $params];
        }
        return $_filters;
    }
    
    protected function applyfilter($filter, $params) {
        if (is_callable($filter) or function_exists($filter)) {
            $func = $filter;
        } else if (isset(self::$modifiers[$filter])) {
            $func = self::$modifiers[$filter];
        } else if (method_exists(TextUtils::class, $filter)) {
            $func = array(TextUtils::class, $filter);
        } else if (substr($filter, 0, 2) == 'is' or substr($filter, 0, 3) == 'not') {
            $func = array(Validator::class, $filter);
        } else {
            return false;
        }
        return call_user_func_array($func, $params);
    }
    
    protected function getValue($data, $key) {
        if (strpos($key, '.')) {
            $keys = explode('.', $key);
            while (count($keys) > 0) {
                $key = array_shift($keys);
                if (!isset($data[$key])) return null;
                $data = $data[$key];
            }
            return $data;
        } else {
            return isset($data[$key]) ? $data[$key] : null;
        }
    }
    
    protected function setValue(&$data, $key, $value) {
        if (strpos($key, '.')) {
            $keys = explode('.', $key);
            $_data = &$data;
            while (count($keys) > 1) {
                $key = array_shift($keys);
                if (!isset($_data[$key])) $_data[$key] = [];
                $_data = &$_data[$key];
            }
            $_data[$key]  = $value;
        } else {
            $data[$key]  = $value;
        }
    }

}