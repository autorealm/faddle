<?PHP namespace Faddle\Middleware;

use Faddle\Http\Request as Request;
use Faddle\Http\Response as Response;

class AuthMiddleware extends BaseMiddleware {
	
	public function __invoke(Request $request, Response $response, callable $out=null) {
		return $this->authenticate();
	}

	/**
	 * authenticate
	 * 
	 * @return true of false
	 */
	function authenticate() {
		//set http auth headers for apache+php-cgi work around
		if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Basic\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
			list($name, $password) = explode(':', base64_decode($matches[1]));
			$_SERVER['PHP_AUTH_USER'] = strip_tags($name);
			$_SERVER['PHP_AUTH_PW'] = strip_tags($password);
		}
		
		//set http auth headers for apache+php-cgi work around if variable gets renamed by apache
		if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && preg_match('/Basic\s+(.*)$/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches)) {
			list($name, $password) = explode(':', base64_decode($matches[1]));
			$_SERVER['PHP_AUTH_USER'] = strip_tags($name);
			$_SERVER['PHP_AUTH_PW'] = strip_tags($password);
		}
		
		if($_SESSION['authenticated'] != 1) { 
			if($_SESSION['login'] != 1) { 
				$_SESSION['login'] = 1;
				$_SESSION['try_count'] = 0;
				$_SESSION['realm'] = time();
				session_regenerate_id(true);
				@header('WWW-Authenticate: Basic realm="'.$_SESSION['realm'].'"');
				@header('HTTP/1.0 401 Unauthorized');
				throw new \Exception('You cancelled the login');
			}
		}
		
		$_SESSION['authenticated'] = 0;
		$_SESSION['try_count']++;
		if ($_SESSION['try_count'] > 4) {
			unset($_SESSION['login']);
			unset($_SESSION['realm']);
			session_destroy();
			throw new \Exception('Too many requests');
		}
		
		$username = array_var($_SERVER, 'PHP_AUTH_USER');
		$password = array_var($_SERVER, 'PHP_AUTH_PW');
		
		if (trim($username == '')) {
			@header('WWW-Authenticate: Basic realm="'.$_SESSION['realm'].'"');
			@header('HTTP/1.0 401 Unauthorized');
			throw new \Exception('Authenticate: Username error');
		}
		
		if (trim($password) == '') {
			@header('WWW-Authenticate: Basic realm="'.$_SESSION['realm'].'"');
			@header('HTTP/1.0 401 Unauthorized');
			throw new \Exception('Authenticate: Password error');
		}
		
		$_SESSION['authenticated'] = 1;
		
		return true;
	}


	/**
	 * Get all HTTP headers
	 * @see https://github.com/symfony/http-foundation/blob/master/ServerBag.php
	 *
	 * @return array
	 *
	 * @SuppressWarnings(PHPMD.Superglobals)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	public static function getHeaders() {
		$headers = array();
		
		$contentHeaders = array('CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true);
		
		foreach ($_SERVER as $key => $value) {
			if (0 === strpos($key, 'HTTP_')) {
				$headers[substr($key, 5)] = $value;
			} elseif (isset($contentHeaders[$key])) { // CONTENT_* are not prefixed with HTTP_
				$headers[$key] = $value;
			}
		}
		
		if (isset($_SERVER['PHP_AUTH_USER'])) {
			$headers['PHP_AUTH_USER'] = $_SERVER['PHP_AUTH_USER'];
			$headers['PHP_AUTH_PW']   = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
			
		} else {
			/*
			 * php-cgi under Apache does not pass HTTP Basic user/pass to PHP by default
			 * For this workaround to work, add these lines to your .htaccess file:
			 * RewriteCond %{HTTP:Authorization} ^(.+)$
			 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
			 *
			 * A sample .htaccess file:
			 * RewriteEngine On
			 * RewriteCond %{HTTP:Authorization} ^(.+)$
			 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
			 * RewriteCond %{REQUEST_FILENAME} !-f
			 * RewriteRule ^(.*)$ app.php [QSA,L]
			 */
			$authorizationHeader = null;
			if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
				$authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
				
			} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
				$authorizationHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			}
			
			if (null !== $authorizationHeader) {
				if (0 === stripos($authorizationHeader, 'basic ')) {
					// Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
					$exploded = explode(':', base64_decode(substr($authorizationHeader, 6)), 2);
					if (count($exploded) == 2) {
						list($headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']) = $exploded;
					}
				
				} elseif (empty($_SERVER['PHP_AUTH_DIGEST']) && (0 === stripos($authorizationHeader, 'digest '))) {
					// In some circumstances PHP_AUTH_DIGEST needs to be set
					$headers['PHP_AUTH_DIGEST'] = $authorizationHeader;
					$_SERVER['PHP_AUTH_DIGEST'] = $authorizationHeader;
				
				} elseif (0 === stripos($authorizationHeader, 'bearer ')) {
					/*
					 * XXX: Since there is no PHP_AUTH_BEARER in PHP predefined variables,
					 *      I'll just set $headers['AUTHORIZATION'] here.
					 *      http://php.net/manual/en/reserved.variables.server.php
					 */
					$headers['AUTHORIZATION'] = $authorizationHeader;
				}
			}
		}
		
		if (isset($headers['AUTHORIZATION'])) {
			return $headers;
		}
		
		// PHP_AUTH_USER/PHP_AUTH_PW
		if (isset($headers['PHP_AUTH_USER'])) {
			$authorization = 'Basic ' . base64_encode($headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW']);
			
			$headers['AUTHORIZATION'] = $authorization;
			
		} elseif (isset($headers['PHP_AUTH_DIGEST'])) {
			$headers['AUTHORIZATION'] = $headers['PHP_AUTH_DIGEST'];
		}
		
		return $headers;
	}

}