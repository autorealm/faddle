<?php namespace Faddle\Helper;

use Faddle\Helper\MemcacheHelper as Storage;

class MemcacheSession implements \SessionHandlerInterface {

	public $handler = null;
	private $prefix = 'mem_sess_';

	/**
	 * Initializes a new Memcache session handler
	 */
	public function __construct($handler, $config=[]) {
		if ($handler instanceof Storage)
			$this->handler = $handler;
		else
			$this->handler = new Storage($config['memcache_config'] ?: []);
		
		session_set_save_handler(
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
		);
		
		if (isset($config['prefix'])) $this->prefix = $config['prefix'];
		if (isset($config['session_name'])) session_name($config['session_name']);
		if (isset($config['session_id'])) session_id($config['session_id']);
		else session_regenerate_id(false);
		
		session_start();
	}

	/**
	* Set a custom storage handler
	* @param Storage $handler storage handler
	*/
	public function setStorage($handler) {
		$this->handler = $handler;
	}

	/**
	* Called by PHP to read session data.
	* @param string $session_id
	* @return string serialized session data
	*/
	public function read($session_id) {
		$session_id = $this->prefix . $session_id;
		// Check for the existance of a cookie with the name of the session id
		// Make sure that the cookie is atleast the size of our hash, otherwise it's invalid
		// Return an empty string if it's invalid.
		if (! $this->handler->has($session_id)) return '';
		try {
			$data = $this->handler->get($session_id);
		} catch (\Exception $ex) {
			$data = '';
		}
		// Return the data, now that it's been verified.
		return $data;
	}

	/**
	* Called by PHP to write out session data
	* @param string $session_id
	* @param string $data
	* @return bool write succeeded
	*/
	public function write($session_id, $data) {
		$session_id = $this->prefix . $session_id;
		$this->handler->set($session_id, $data);
	}

	/**
	* Called by PHP to destroy the session
	* @param string $session_id
	* @return bool true success
	*/
	public function destroy($session_id) {
		$session_id = $this->prefix . $session_id;
		$this->handler->delete($session_id);
	}

	// In the context of cookies, these three methods are unneccessary, but must
	// be implemented as part of the SessionHandlerInterface.
	public function open($save_path, $name) { return true; }
	public function gc($maxlifetime) { return true; }
	public function close() { return true; }

}
