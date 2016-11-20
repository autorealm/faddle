<?php namespace Faddle\Storage;

/**
 * 闪存类
 */
class Flash {

	private $previous = array();
	private $next = array();
	
	public function __construct() {
		$this->read();
	}
	
	public static function &instance() {
		static $instance;
		if (! $instance instanceof self) {
			$instance = new self();
		}
		return $instance;
	}
	
	public function get($key) {
		return isset($this->previous[trim($key)]) ? $this->previous[trim($key)] : null;
	}
	
	public function add($key, $value) {
		$this->next[trim($key)] = $value;
		$this->write();
	}
	
	public function remove($key) {
		if (isset($this->next[trim($key)])) {
			unset($this->next[trim($key)]);
		}
		$this->write();
	}
	
	public function clear() {
		$this->next = array();
	}
	
	protected function read() {
		$flash_data = array_var($_SESSION, 'flash_data');
		if (!is_null($flash_data)) {
			if (is_array($flash_data)) {
				$this->previous = $flash_data;
			}
			unset($_SESSION['flash_data']);
		}
	}
	
	protected function write() {
		$_SESSION['flash_data'] = $this->next;
	}

	/**
	 * 开始会话并返回当前会话(Session)ID
	 *
	 * @return string|false
	 */
	public function startSession() {
		if (session_id() === '') {
			// Attempt to start a session
			session_start();
			$this->session_id = session_id() ?: false;
		}
		return $this->session_id;
	}

	/**
	 * Stores a flash message of $type
	 *
	 * @param string $msg       The message to flash
	 * @param string $type      The flash message type
	 * @param array $params     Optional params to be parsed by markdown
	 * @return void
	 */
	public function flash($msg, $type = 'info', $params = null) {
		$this->startSession();
		if (is_array($type)) {
			$params = $type;
			$type = 'info';
		}
		if (!isset($_SESSION['__flashes'])) {
			$_SESSION['__flashes'] = array($type => array());
		} elseif (!isset($_SESSION['__flashes'][$type])) {
			$_SESSION['__flashes'][$type] = array();
		}
		$_SESSION['__flashes'][$type][] = $this->markdown($msg, $params);
	}

	/**
	 * Returns and clears all flashes of optional $type
	 *
	 * @param string $type  The name of the flash message type
	 * @return array
	 */
	public function flashes($type = null) {
		$this->startSession();
		if (!isset($_SESSION['__flashes'])) {
			return array();
		}
		if (null === $type) {
			$flashes = $_SESSION['__flashes'];
			unset($_SESSION['__flashes']);
		} else {
			$flashes = array();
			if (isset($_SESSION['__flashes'][$type])) {
				$flashes = $_SESSION['__flashes'][$type];
				unset($_SESSION['__flashes'][$type]);
			}
		}
		
		return $flashes;
	}

	/**
	 * Render a text string as markdown
	 * 
	 * @param string $str   The text string to parse
	 * @param array $args   Optional arguments to be parsed by markdown
	 * @return string
	 */
	public static function markdown($str, $args = null) {
		// Create our markdown parse/conversion regex's
		$md = array(
			'/\[([^\]]++)\]\(([^\)]++)\)/' => '<a href="$2">$1</a>',
			'/\*\*([^\*]++)\*\*/'          => '<strong>$1</strong>',
			'/\*([^\*]++)\*/'              => '<em>$1</em>'
		);
		
		// Let's make our arguments more "magical"
		$args = func_get_args(); // Grab all of our passed args
		$str = array_shift($args); // Remove the initial arg from the array (and set the $str to it)
		if (isset($args[0]) && is_array($args[0])) {
			$args = $args[0];
		}
		
		// Encode our args so we can insert them into an HTML string
		foreach ($args as &$arg) {
			$arg = htmlentities($arg, ENT_QUOTES, 'UTF-8');
		}
		
		// Actually do our markdown conversion
		return vsprintf(preg_replace(array_keys($md), $md, $str), $args);
	}

}
