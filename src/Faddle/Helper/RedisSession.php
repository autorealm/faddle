<?php namespace Faddle\Helper;

use Redis;

class RedisSession implements \SessionHandlerInterface {

	// stores settings
	protected $settings;
	// stores redis object
	protected $redis;
	protected $redis_config = ['scheme' => 'tcp', 'host' => '127.0.0.1', 'port' => '6379', 'db' => 12];
	protected $session_stat = array();

	/**
	 * 构造
	 * @param (Array) $settings
	 * @return void
	 */
	public function __construct($settings = array()) {
		// A neat way of doing setting initialization with default values
		$this->settings = array_merge(array(
			'session.name'		=> 'faddle_session',
			'session.id'		=> '',
			'session.expires'	=> ini_get('session.gc_maxlifetime'),
			'cookie.lifetime'	=> 0,
			'cookie.path'		=> '/',
			'cookie.domain'		=> '',
			'cookie.secure'		=> false,
			'cookie.httponly'	=> true
		), $settings);
		if (isset($settings['session.cookie_domain']))
			ini_set('session.cookie_domain', $settings['session.cookie_domain']);
		if (isset($settings['redis.config']))
			$this->redis_config = array_merge($this->redis_config, $settings['redis.config']);
		// if the setting for the expire is a string convert it to an int
		if ( is_string($this->settings['session.expires']) )
			$this->settings['session.expires'] = intval($this->settings['session.expires']);
		// cookies blah!
		session_name($this->settings['session.name']);
		
		session_set_cookie_params(
			$this->settings['cookie.lifetime'],
			$this->settings['cookie.path'],
			$this->settings['cookie.domain'],
			$this->settings['cookie.secure'],
			$this->settings['cookie.httponly']
		);
		// overwrite the default session handler to use this classes methods instead
		session_set_save_handler(
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
		);
		
		if (! empty($this->settings['session.id']))
			session_id($this->settings['session.id']);
		else session_regenerate_id(false);
		
		// start our session
		session_start();
	}

	/**
	 * 打开
	 * @return true
	 */
	public function open( $session_path, $session_name ) {
		$this->redis = new Redis();
		$this->redis->pconnect($this->redis_config['host'], $this->redis_config['port'], $this->redis_config['db']);
		//$this->redis->select($session_name);
		return true;
	}

	/**
	 * 关闭
	 * @return true
	 */
	public function close() {
		// Destroy the session cookie
		$params = session_get_cookie_params();
		
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
		);
		
		session_unset();
		session_destroy();
		return $this->redis->close();
	}

	/**
	 * 读取
	 * @return Array
	 */
	public function read($session_id) {
		$key = "{$this->settings['session.name']}:{$session_id}";
		$session_data = $this->redis->get($key);
		if ($session_data === NULL) {
			return '';
		}
		$this->redis->session_stat[$key] = md5($session_data);
		
		return $session_data;
	}

	/**
	 * 写入
	 * @return True|False
	 */
	public function write( $session_id, $session_data ) {
		$key = "{$this->settings['session.name']}:{$session_id}";
		$lifetime = $this->settings['session.expires'];
		//check if anything changed in the session, only send if has changed
		if (!empty($this->redis->session_stat[$key]) && $this->redis->session_stat[$key] == md5($session_data)) {
			//just sending EXPIRE should save a lot of bandwidth!
			$this->redis->setTimeout($key, $lifetime);
		} else {
			$this->redis->set($key, $session_data, $lifetime);
		}
	}

	/**
	 * 销毁
	 * @return true
	 */
	public function destroy($session_id) {
		$this->redis->delete("{$this->settings['session.name']}:{$session_id}");
		return true;
	}

	/**
	 * 回收
	 * @return void
	 */
	public function gc($maxlifetime) {
		return true;
	}

	/**
	 * 析构
	 * @return void
	 */
	public function __destruct() {
		session_write_close();
	}


}
