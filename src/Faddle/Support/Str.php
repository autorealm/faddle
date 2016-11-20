<?php namespace Faddle\Support;

class Str {


	/**
	 * Determine if a given string contains a given substring.
	 *
	 * @param  string  $haystack
	 * @param  string|array  $needles
	 * @return bool
	 */
	public static function contains($haystack, $needles) {
		foreach ((array) $needles as $needle) {
			if ($needle != '' && strpos($haystack, $needle) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if a given string starts with a given substring.
	 *
	 * @param  string  $haystack
	 * @param  string|array  $needles
	 * @return bool
	 */
	public static function startsWith($haystack, $needles) {
		foreach ((array) $needles as $needle) {
			if ($needle != '' && strpos($haystack, $needle) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if a given string ends with a given substring.
	 *
	 * @param  string  $haystack
	 * @param  string|array  $needles
	 * @return bool
	 */
	public static function endsWith($haystack, $needles) {
		foreach ((array) $needles as $needle) {
			if ((string) $needle === substr($haystack, -strlen($needle))) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The cache of studly-cased words.
	 *
	 * @var array
	 */
	protected static $studlyCache = [];
	
	/**
	 * Convert a value to studly caps case.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public static function studly($value) {
		$key = $value;
		if (isset(static::$studlyCache[$key])) {
			return static::$studlyCache[$key];
		}
		$value = ucwords(str_replace(['-', '_'], ' ', $value));
		return static::$studlyCache[$key] = str_replace(' ', '', $value);
	}

	/**
	 * The cache of snake-cased words.
	 *
	 * @var array
	 */
	protected static $snakeCache = [];

	/**
	 * Convert a string to snake case.
	 *
	 * @param  string  $value
	 * @param  string  $delimiter
	 * @return string
	 */
	public static function snake($value, $delimiter = '_') {
		$key = $value.$delimiter;
		if (isset(static::$snakeCache[$key])) {
			return static::$snakeCache[$key];
		}
		if (! ctype_lower($value)) {
			$value = preg_replace('/\s+/', '', $value);
			$value = strtolower(preg_replace('/(.)(?=[A-Z])/', '$1'.$delimiter, $value));
		}
		return static::$snakeCache[$key] = $value;
	}

	/**
	 * Generates a universally unique identifier (UUID v4) according to RFC 4122
	 * Version 4 UUIDs are pseudo-random!
	 *
	 * Returns Version 4 UUID format: xxxxxxxx-xxxx-4xxx-Yxxx-xxxxxxxxxxxx where x is
	 * any random hex digit and Y is a random choice from 8, 9, a, or b.
	 *
	 * @see http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
	 *
	 * @return string
	 */
	public static function uuid() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			// 16 bits for "time_mid"
			mt_rand(0, 0xffff),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand(0, 0x0fff) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand(0, 0x3fff) | 0x8000,
			// 48 bits for "node"
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0xffff)
		);
	}

	public static function is_serialized($data) {
		$data = trim($data);
		if ('N;' == $data)
			return true;
		if (!preg_match('/^([adObis]):/', $data, $badions))
			return false;
		switch ($badions[1]) {
			case 'a':
			case 'O':
			case 's':
				if (preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data))
					return true;
				break;
			case 'b':
			case 'i':
			case 'd':
				if (preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data))
					return true;
				break;
		}
		return false;
	}

}
