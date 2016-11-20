<?php namespace Faddle\Common\Util;

/**
 * 常用开放API方法类
 */
class OpenApi extends Common {

	public static function Captcha($value = null, $background = null) {
		if (strlen(session_id()) > 0)
		{
			if (is_null($value) === true)
			{
				$result = parent::CURL('http://services.sapo.pt/Captcha/Get/');

				if (is_object($result = parent::DOM($result, '//captcha', 0)) === true)
				{
					$_SESSION[__METHOD__] = parent::Value($result, 'code');

					if (strcasecmp('ok', parent::Value($result, 'msg')) === 0)
					{
						$result = parent::Value($result, 'id');

						if (strlen($background = ltrim($background, '#')) > 0)
						{
							$result .= sprintf('&background=%s', $background);

							if (hexdec($background) < 0x7FFFFF)
							{
								$result .= sprintf('&textcolor=%s', 'ffffff');
							}
						}

						return preg_replace('~^https?:~', '', parent::URL('http://services.sapo.pt/', '/Captcha/Show/', array('id' => strtolower($result))));
					}
				}
			}

			return (strcasecmp(trim($value), parent::Value($_SESSION, __METHOD__)) === 0);
		}

		return false;
	}

	public static function Country($country = null, $language = 'en', $ttl = 604800)
	{
		$key = array(__METHOD__, $language);
		$result = parent::Cache(vsprintf('%s:%s', $key));

		if ($result === false)
		{
			if (($countries = self::CURL('http://www.geonames.org/countryInfoJSON', array('lang' => $language))) !== false)
			{
				if (is_array($countries = parent::Value(json_decode($countries, true), 'geonames')) === true)
				{
					$result = array();

					foreach ($countries as $value)
					{
						$result[$value['countryCode']] = $value['countryName'];
					}

					$result = parent::Cache(vsprintf('%s:%s', $key), parent::Sort($result, false), $ttl);
				}
			}
		}

		if ((isset($country) === true) && (is_array($result) === true))
		{
			return parent::Value($result, strtoupper($country));
		}

		return $result;
	}

	public static function Currency($input, $output, $value = 1, $ttl = null)
	{
		$key = array(__METHOD__);
		$result = parent::Cache(vsprintf('%s', $key));

		if ($result === false)
		{
			$result = array();
			$currencies = parent::DOM(self::CURL('http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'), '//cube/cube/cube');

			if (is_array($currencies) === true)
			{
				$result['EUR'] = 1;

				foreach ($currencies as $currency)
				{
					$result[strval($currency['currency'])] = 1 / floatval($currency['rate']);
				}

				if (is_null($ttl) === true)
				{
					$ttl = parent::Date('U', '6 PM', true) - $_SERVER['REQUEST_TIME'];

					if ($ttl < 0)
					{
						$ttl = parent::Date('U', '@' . $ttl, true, '+1 day');
					}

					$ttl = round(max(3600, $ttl / 2));
				}

				$result = parent::Cache(vsprintf('%s', $key), $result, $ttl);
			}
		}

		if ((is_array($result) === true) && (isset($result[$input], $result[$output]) === true))
		{
			return floatval($value) * $result[$input] / $result[$output];
		}

		return false;
	}

	public static function GeoIP($ip = null, $proxy = false, $ttl = 86400)
	{
		if (extension_loaded('geoip') !== true)
		{
			$key = array(__METHOD__, $ip, $proxy);
			$result = parent::Cache(vsprintf('%s:%s:%b', $key));

			if ($result === false)
			{
				if (($result = self::CURL('http://api.wipmania.com/' . $ip)) !== false)
				{
					$result = parent::Cache(vsprintf('%s:%s:%b', $key), trim($result), $ttl);
				}
			}

			return $result;
		}

		return (geoip_db_avail(GEOIP_COUNTRY_EDITION) === true) ? geoip_country_code_by_name($ip) : false;
	}

	public static function SMS($to, $from, $message, $username, $password, $unicode = false)
	{
		$data = array();
		$message = trim($message);

		if (isset($username, $password) === true)
		{
			$data['username'] = $username;
			$data['password'] = $password;

			if (isset($to, $from, $message) === true)
			{
				$message = static::Reduce($message, ' ');

				if (preg_match('~[^\x20-\x7E]~', $message) > 0)
				{
					$message = static::Filter($message);

					if ($unicode === true)
					{
						$message = static::str_split($message);

						foreach ($message as $key => $value)
						{
							$message[$key] = sprintf('%04x', static::ord($value));
						}

						$message = implode('', $message);
					}

					$message = static::Unaccent($message);
				}

				if (is_array($data) === true)
				{
					$data['to'] = $to;
					$data['from'] = $from;
					$data['type'] = (preg_match('^(?:[[:xdigit:]]{4})*$', $message) > 0);

					if ($data['type'] === true)
					{
						$data['hex'] = $message;
					}

					else if ($data['type'] === false)
					{
						$data['text'] = $message;
					}

					$data['type'] = intval($data['type']) + 1;
					$data['maxconcat'] = '10';
				}

				return (strpos(self::CURL('https://www.intellisoftware.co.uk/smsgateway/sendmsg.aspx', $data, 'POST'), 'ID:') !== false) ? true : false;
			}

			return intval(preg_replace('~^BALANCE:~', '', self::CURL('https://www.intellisoftware.co.uk/smsgateway/getbalance.aspx', $data, 'POST')));
		}

		return false;
	}

	public static function VIES($vatin, $country, $key = 'valid', $default = null)
	{
		if ((preg_match('~[A-Z]{2}~', $country) > 0) && (preg_match('~[0-9A-Z.+*]{2,12}~', $vatin) > 0))
		{
			try
			{
				if (is_object($soap = new SoapClient('http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl', array('exceptions' => true))) === true)
				{
					return static::Value($soap->__soapCall('checkVat', array(array('countryCode' => $country, 'vatNumber' => $vatin))), $key, $default);
				}
			}

			catch (SoapFault $e)
			{
				return $default;
			}
		}

		return false;
	}

	public static function Whois($domain)
	{
		if (strpos($domain, '.') !== false)
		{
			$tld = strtolower(ltrim(strrchr($domain, '.'), '.'));
			$socket = @fsockopen($tld . '.whois-servers.net', 43);

			if (is_resource($socket) === true)
			{
				if (preg_match('~com|net~', $tld) > 0)
				{
					$domain = sprintf('domain %s', $domain);
				}

				if (fwrite($socket, $domain . "\r\n") !== false)
				{
					$result = null;

					while (feof($socket) !== true)
					{
						$result .= fread($socket, 8192);
					}

					return $result;
				}
			}
		}

		return false;
	}

	public static function Calculator($input, $output, $query = 1)
	{
		$data = array
		(
			'q' => $query . $input . '=?' . $output,
		);

		if (($result = parent::CURL('http://www.google.com/ig/calculator', $data)) !== false)
		{
			$result = preg_replace(array('~([{,])~', '~:[[:blank:]]+~'), array('$1"', '":'), parent::Filter($result, true));

			if ((is_array($result = json_decode($result, true)) === true) && (strlen(parent::Value($result, 'error')) == 0))
			{
				return parent::Value($result, 'rhs');
			}
		}

		return false;
	}

	public static function Geocode($query, $country = null, $reverse = false)
	{
		$data = array
		(
			'address' => $query,
			'region' => $country,
			'sensor' => 'false',
		);

		if (($result = parent::CURL('http://maps.googleapis.com/maps/api/geocode/json', $data)) !== false)
		{
			return parent::Value(json_decode($result, true), ($reverse === true) ? array('results', 0, 'formatted_address') : array('results', 0, 'geometry', 'location'));
		}

		return false;
	}

	public static function QR($query, $size = '500x500', $quality = 'Q')
	{
		$data = array
		(
			'chl' => $query,
			'chld' => sprintf('%s|2', $quality),
			'choe' => 'UTF-8',
			'chs' => $size,
			'cht' => 'qr',
		);

		return preg_replace('~^https?:~', '', parent::URL('http://chart.googleapis.com/', '/chart', $data));
	}

	public static function Search($query, $class = 'web', $start = 0, $results = 4, $arguments = null)
	{
		$data = array
		(
			'q' => $query,
			'rsz' => $results,
			'start' => intval($start),
			'userip' => ph()->HTTP->IP(null, false),
			'v' => '1.0',
		);

		if (($result = parent::CURL('http://ajax.googleapis.com/ajax/services/search/' . $class, $data)) !== false)
		{
			return (is_array($result = parent::Value(json_decode($result, true), 'responseData')) === true) ? $result : false;
		}

		return false;
	}

	public static function Speed($url)
	{
		if (($result = parent::CURL('http://pagespeed.googlelabs.com/run_pagespeed', array('url' => $url))) !== false)
		{
			return parent::Value(json_decode($result, true), 'results');
		}

		return false;
	}

}
