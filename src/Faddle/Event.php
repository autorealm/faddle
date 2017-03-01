<?PHP namespace Faddle;

class Event {

	private $listeners = array();
	protected $events = array();
	
	public function __construct() {
		
	}
	
	public function __destruct() {
		unset($this->listeners);
		unset($this->events);
	}
	
	public static function build($event=array(), $listener=null) {
		$self =  new self();
		$self->set($event, $listener);
		return $self;
	}
	
	public function __call($method, $args) {
		if (preg_match('/^on[A-Z_]{1}.+/', $method)) {
			$event_name = lcfirst(substr($method, 2));
			if (method_exists($this, 'fire')) {
				array_unshift($args, $event_name);
				return call_user_func_array(array($this, 'fire'), $args);
			}
		}
	}
	
	/**
	 * 注册某事件监听器
	 * @param string $event 事件名称
	 * @param mixed $callback 回调函数或数组参数
	 * @param mixed $extras 附加参数
	 * @param mixed $append 是否顺序加入，否则放在队列开头
	 * @return Event
	 */
	public function on($event, $callback, $extras=null, $append=true) {
		$event = strtolower($event);
		if (in_array($event, ($this->events))) {
			if (!isset($this->listeners[$event])) {
				$this->listeners[$event] = array();
			}
			if ($append) $this->listeners[$event][] = array($callback, (array) $extras);
			else array_unshift($this->listeners[$event], array($callback, (array) $extras));
			//return true;
		} else {
			//return false;
		}
		return $this;
	}
	
	/**
	 * 注册一次性的某事件监听器
	 * @param string $event 事件名称
	 * @param mixed $callback 回调函数或数组参数
	 * @return boolean|Event
	 */
	public function once($event, $callback) {
		$event = strtolower($event);
		if (in_array($event, ($this->events))) {
			$this->listeners[$event][] = array($callback, array('times' => 1));
			
		} else {
			return false;
		}
		return $this;
	}
	
	/**
	 * 取消某事件的监听器
	 * @param string $event 事件名称
	 * @param mixed $callback 回调函数或数组参数
	 * @return Event
	 */
	public function off($event, $callback=null) {
		$event = strtolower($event);
		if (!empty($this->listeners[$event])) {
			if (is_null($callback)) {
				unset($this->listeners[$event]);
				$this->listeners[$event] = array();
			} else {
				if (($key = array_search(array($callback), $this->listeners[$event])) === false) {
					foreach ($this->listeners[$event] as $k => $v) {
						if ($callback === $v[0]) {
							$key = $k;
							break;
						}
					}
				}
				if ($key !== false) {
					unset($this->listeners[$event][$key]);
					$this->listeners[$event] = array_values($this->listeners[$event]);
				}
			}
		}
		return $this;
	}
	
	/**
	 * 触发指定事件
	 * @param string $event 事件名称
	 * @param mixed $params... 参数
	 * @return mixed
	 */
	public function fire($event) {
		if (func_num_args() > 1) $params = array_slice(func_get_args(), 1); else $params = null;
		return $this->trigger($event, $params, null, true);
	}
	
	/**
	 * 触发指定事件
	 * @param string $event 事件名称
	 * @param array $params 参数数组
	 * @param callable $then 执行后的回调函数
	 * @param boolean $by_array 参数是否作为数组传递
	 * @return mixed
	 */
	public function trigger($event, $params=null, $then=null, $by_array=false) {
		$event = strtolower($event);
		if ($by_array) $params = (array) $params;
		else $params = array($params);
		if (! $params) $params = array();
		
		if (empty($this->listeners[$event])) return false;
		if ($then and is_callable($then)) $hasthen = true; else $hasthen = false;
		foreach ($this->listeners[$event] as $i => &$listener) {
			if (is_array($listener)) { //此处回调带扩展参数
				$extras = isset($listener[1]) ? $listener[1] : null;
				$callback = $listener[0];
				if ($extras and is_array($extras)) {
					if (array_key_exists('times', $extras)) $times = & $extras['times'];
					if (array_key_exists('params', $extras)) $_params = (array) $extras['params'];
				}
			} else $callback = $listener;
			if (isset($times)) {
				if (intval($times) <= 0) {
					unset($this->listeners[$event][$i]);
					continue;
				} else $times--;
			}
			if ($callback instanceof \Closure or is_callable($callback)) {
				$return = call_user_func_array($callback, $params);
			} else {
				$callback = (string) $callback; 
				$calls = explode('@', $callback);
				if (count($calls) <= 1) {
					if (function_exists($calls[0])) {
						$return = call_user_func_array($calls[0], $params);
					} else {
						$return = null;
					}
				} else {
					$method = $calls[1];
					if (class_exists($class = $calls[0])) {
						if ($_params) {
							if (count($_params == 1)) $callback = new $class($_params[0]);
							else if (count($_params == 2)) $callback = new $class($_params[0], $_params[1]);
							else $callback = new $class($_params[0], $_params[1] 
								, (isset($_params[2])) ? $_params[2] : null
								, (isset($_params[3])) ? $_params[3] : null
								, (isset($_params[4])) ? $_params[4] : null);
						} else $callback = new $class();
					} else $callback = false;
					if ($callback and method_exists($callback, $method)) {
						$return = call_user_func_array(array($callback, $method), $params);
					} else {
						$return = null;
					}
				}
			}
			if ($hasthen) {
				$_return = call_user_func($then, $return);
				if ($_return) $params = array($_return); //返回值作为参数继续传递
			}
			if ($return === false) return true; //阻止事件再传递
		}
		return $return;
	}
	
	/**
	 * 设置事件名称及默认方法
	 * 
	 * @param mixed $key Key
	 * @param mixed $value Value
	 */
	public function set($key, $value=null) {
		if (is_array($key)) {
			foreach ($key as $event) {
				$event = strtolower((string)$event);
				if (! $this->has($event))
					$this->events[] = $event;
				if (! is_null($value) and ! $this->has($event, $value))
					$this->listeners[$event][] = $value;
			}
		} else {
			$key = strtolower($key);
			$this->events[] = $key;
			if (! is_null($value) and ! $this->has($key, $value))
				$this->listeners[$key][] = $value;
		}
		
	}

	/**
	 * 检查事件是否存在
	 * 
	 * @param string $key  事件名称
	 * @param mixed $value 事件回调
	 * @return bool
	 */
	public function has($key, $value=null) {
		if (! is_null($value) and isset($this->listeners[$key])) {
			if (($key = array_search(array($value), $this->listeners[$key])) === false) {
				foreach ($this->listeners[$key] as $k => $v) {
					if ($value === $v[0]) {
						$key = $k;
						break;
					}
				}
			}
			return $key !== false;
		}
		return (array_search($key, $this->events)) !== false;
	}

	/**
	 * 清空事件或清除某个事件
	 *
	 * @param string $key Key
	 */
	public function clear($key=null) {
		if (is_null($key)) {
			$this->events = array();
			$this->listeners = array();
		} else {
			$key = strtolower($key);
			if (($idx = array_search($key, $this->events)) !== false) {
				unset($this->events[$idx]);
				unset($this->listeners[$key]);
			}
		}
	}

}
