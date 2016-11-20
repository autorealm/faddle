<?php namespace Faddle\Helper;

if (! defined('SESSION_PREFIX')) define('SESSION_PREFIX', '');

/**
 * Session Class
 */
class SessionHelper {

    /**
     * Determine if session has started.
     *
     * @var boolean
     */
    private static $sessionStarted = false;

    /**
     * if session has not started, start sessions
     */
    public static function init() {
        //不使用 GET/POST 变量方式
        ini_set('session.use_trans_sid', 0);
        //设置垃圾回收最大生存时间
        ini_set('session.gc_maxlifetime', self::$cache_lifetime);
        //使用 COOKIE 保存 SESSION ID 的方式
        ini_set('session.use_cookies', 1);
        ini_set('session.cookie_path', '/');
        //多主机共享保存 SESSION ID 的 COOKIE
        ini_set('session.cookie_domain', self::$sess_domain);
        //将 session.save_handler 设置为 user，而不是默认的 files
        session_module_name('user');
        //定义 SESSION 各项操作所对应的方法名
        session_set_save_handler(
            array($this, 'open'),
            array($this, 'close'),
            array($this, 'get'),
            array($this, 'set'),
            array($this, 'delete'),
            array($this, 'gc')
        );
        if (self::$sessionStarted == false) {
            session_start();
            self::$sessionStarted = true;
        }
        return true;
    }

    /**
     * Add value to a session.
     *
     * @param string $key   name the data to save
     * @param string $value the data to save
     */
    public static function set($key, $value = false) {
        /**
        * Check whether session is set in array or not
        * If array then set all session key-values in foreach loop
        */
        if (is_array($key) && $value === false) {
            foreach ($key as $name => $value) {
                $_SESSION[SESSION_PREFIX.$name] = $value;
            }
        } else {
            $_SESSION[SESSION_PREFIX.$key] = $value;
        }
    }

    public static function delete($key)
    {
        return unset($_SESSION[SESSION_PREFIX.$key]);
    }

    /**
     * Extract item from session then delete from the session, finally return the item.
     *
     * @param  string $key item to extract
     *
     * @return mixed|null      return item or null when key does not exists
     */
    public static function pull($key) {
        if (isset($_SESSION[SESSION_PREFIX.$key])) {
            $value = $_SESSION[SESSION_PREFIX.$key];
            unset($_SESSION[SESSION_PREFIX.$key]);
            return $value;
        }
        return null;
    }

    /**
     * Get item from session.
     *
     * @param  string  $key       item to look for in session
     * @param  boolean $secondkey if used then use as a second key
     *
     * @return mixed|null         returns the key value, or null if key doesn't exists
     */
    public static function get($key, $secondkey = false) {
        if ($secondkey == true) {
            if (isset($_SESSION[SESSION_PREFIX.$key][$secondkey])) {
                return $_SESSION[SESSION_PREFIX.$key][$secondkey];
            }
        } else {
            if (isset($_SESSION[SESSION_PREFIX.$key])) {
                return $_SESSION[SESSION_PREFIX.$key];
            }
        }
        return null;
    }

    /**
     * id
     *
     * @return string with the session id.
     */
    public static function id() {
        return session_id();
    }

    public static function setId($session_id)
    {
        session_id($session_id);
    }

    /**
     * Regenerate session_id.
     *
     * @return string session_id
     */
    public static function regenerate() {
        session_regenerate_id(true);
        return session_id();
    }

    /**
     * Return the session array.
     *
     * @return array of session indexes
     */
    public static function session() {
        return $_SESSION;
    }


    /**
     * Empties and destroys the session.
     *
     * @param  string $key - session name to destroy
     * @param  boolean $prefix - if set to true clear all sessions for current SESSION_PREFIX
     *
     */
    public static function destroy($key = '', $prefix = false) {
        /** only run if session has started */
        if (self::$sessionStarted == true) {
            /** if key is empty and $prefix is false */
            if ($key =='' && $prefix == false) {
                session_unset();
                session_destroy();
            } elseif ($prefix == true) {
                /** clear all session for set SESSION_PREFIX */
                foreach($_SESSION as $key => $value) {
                    if (strpos($key, SESSION_PREFIX) === 0) {
                        unset($_SESSION[$key]);
                    }
                }
            } else {
                /** clear specified session key */
                unset($_SESSION[SESSION_PREFIX.$key]);
            }
        }
    }

    /**
     * 内存回收
     * @param   NULL
     * @return  Bool    true/FALSE
     */
    public static function gc()
    {
        return true;
    }
     
    public static function open()
    {
        return true;
    }
    
    public static function close()
    {
        return true;
    }

}
