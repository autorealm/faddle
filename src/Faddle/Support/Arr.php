<?php namespace Faddle\Support;



class Arr {


	/**
	 * Set an array item to a given value using "dot" notation.
	 *
	 * If no key is given to the method, the entire array will be replaced.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return array
	 */
	public static function set(&$array, $key, $value) {
		if (is_null($key)) {
			return $array = $value;
		}
		$keys = explode('.', $key);
		while (count($keys) > 1) {
			$key = array_shift($keys);
			// If the key doesn't exist at this depth, we will just create an empty array
			// to hold the next value, allowing us to create the arrays to hold final
			// values at the correct depth. Then we'll keep digging into the array.
			if (! isset($array[$key]) || ! is_array($array[$key])) {
				$array[$key] = [];
			}
			$array = &$array[$key];
		}
		$array[array_shift($keys)] = $value;
		return $array;
	}

	/**
	 * Get an item from an array using "dot" notation.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public static function get($array, $key, $default = null) {
		if (is_null($key)) {
			return $array;
		}
		if (isset($array[$key])) {
			return $array[$key];
		}
		foreach (explode('.', $key) as $segment) {
			if (! is_array($array) || ! array_key_exists($segment, $array)) {
				return value($default);
			}
			$array = $array[$segment];
		}
		return $array;
	}

	/**
	 * Check if an item exists in an array using "dot" notation.
	 *
	 * @param  array   $array
	 * @param  string  $key
	 * @return bool
	 */
	public static function has($array, $key) {
		if (empty($array) || is_null($key)) {
			return false;
		}
		if (array_key_exists($key, $array)) {
			return true;
		}
		foreach (explode('.', $key) as $segment) {
			if (! is_array($array) || ! array_key_exists($segment, $array)) {
				return false;
			}
			$array = $array[$segment];
		}
		return true;
	}

	/**
	 * Get all of the given array except for a specified array of items.
	 *
	 * @param  array  $array
	 * @param  array|string  $keys
	 * @return array
	 */
	public static function except($array, $keys) {
		static::forget($array, $keys);
		return $array;
	}

	/**
	 * Remove one or many array items from a given array using "dot" notation.
	 *
	 * @param  array  $array
	 * @param  array|string  $keys
	 * @return void
	 */
	public static function forget(&$array, $keys) {
		$original = &$array;
		$keys = (array) $keys;
		if (count($keys) === 0) {
			return;
		}
		foreach ($keys as $key) {
			$parts = explode('.', $key);
			// clean up before each pass
			$array = &$original;
			while (count($parts) > 1) {
				$part = array_shift($parts);
				if (isset($array[$part]) && is_array($array[$part])) {
					$array = &$array[$part];
				} else {
					continue 2;
				}
			}
			unset($array[array_shift($parts)]);
		}
	}

	/**
	 * array_map 的深度版本
	 */
	public static function map(array $array, $callback, $on_nonscalar=false) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$args = array($value, $callback, $on_nonscalar);
				$array[$key] = call_user_func_array(array(__CLASS__, __FUNCTION__), $args);
			} elseif (is_scalar($value) || $on_nonscalar) {
				$array[$key] = call_user_func($callback, $value);
			}
		}
		return $array;
	}

	/**
	 * array_merge 的深度版本
	 */
	public static function merge(array $dest, array $src, $appendIntegerKeys=true) {
		foreach ($src as $key => $value) {
			if (is_int($key) and $appendIntegerKeys) {
				$dest[] = $value;
			} elseif (isset($dest[$key]) and is_array($dest[$key]) and is_array($value)) {
				$dest[$key] = static::merge($dest[$key], $value, $appendIntegerKeys);
			} else {
				$dest[$key] = $value;
			}
		}
		return $dest;
	}

	/**
	 * stripslashes 的深度版本
	 */
	public static function strips($value) {
		if (is_array($value)) {
			$value = array_map(array(__CLASS__, __FUNCTION__), $value);
		} elseif (is_object($value)) {
			$vars = get_object_vars($value);
			foreach ($vars as $key => $data) {
				$value->{$key} = static::strips($data);
			}
		} elseif (is_string($value)) {
			$value = stripslashes($value);
		}
		return $value;
	}

	/**
	 * parse_args
	 */
	public static function parse($args, $defaults = array()) {
		if (is_object($args)) {
			$r = get_object_vars($args);
		} elseif (is_array($args)) {
			$r = &$args;
		} else {
			if (preg_match('/(?:^\{.*\}$|^\[.*\]$)/', $args) and strpos($args, ':')) {
				$r = json_decode($args, true) ?: array();
			} else {
				parse_str($args, $r);
				if (get_magic_quotes_gpc()) {
					$r = static::strips($r);
				}
			}
		}
		if (is_array($defaults)) {
			return array_merge($defaults, $r);
		}
		return $r;
	}

}
