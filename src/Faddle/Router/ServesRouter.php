<?php namespace Faddle\Router;

use Faddle\App;
use Faddle\Router\Router;


/* JSON 配置文件格式
{
	"name": "Faddle Serves",
	"description": "Serves Config File",
	"list": [
		{
			"name": "Serve", //路由器名称
			"domain": ["serve.sample.com"], //域名。数组或字符串
			//"group": "serve", //组名。附在主路由中作为蓝图，不可与域名并存
			"chdir": "serve", //工作目录。可选，可以是相对路径(自定义的或配置文件所在目录)，或者绝对路径(以'/'开头)
			"router": "serve/routes.php", //路由器对象，文件必需返回该对象
			//"index": "serve/index.php", //自定义的索引文件，不可与路由器对象并存
			"routes": { //自定义的路由
				"test1": "serve/test1.php",
				"test2": "serve/test2.php"
			},
			"dependencies": {} //依赖的类，可以是单独类的路径或者键是类名，值是类路径
		}
	]
}
*/

/**
 * 服务路由器
 */
class ServesRouter {

	/**
	 * 组合其他域名路由器到主应用路由器中
	 * 
	 * @param string $servesfile Serves JSON 配置文件
	 * @param string $filepath 文件所在路径，可选，默认是配置文件所在的文件夹。
	 * @param mixed $default_route 默认路由，可选，当未匹配到路由时会进入该路由。
	 * @return boolean
	 */
	public static function load($servesfile, $filepath=null, $default_route=null) {
		if (!file_exists($servesfile)) {
			return false;
		}
		$serves = file_get_contents($servesfile);
		$serves = json_decode($serves, true);
		if (!is_array($serves)) return false;
		$filepath = realpath($filepath ?: dirname($servesfile));
		$app = App::getInstance();
		foreach ($serves['list'] as $serve) {
			if ((!isset($serve['domain']) or empty($serve['domain']))
				and (!isset($serve['group']) or empty($serve['group']))) continue; //需要域名或组名
			if (!isset($serve['index']) and !isset($serve['router'])) continue; //需要索引或路由器文件
			$_name = $serve['name'];
			$_domain = null;
			$_group = '';
			if (isset($serve['domain'])) {
				$_domain = $serve['domain'];
				if (! static::check_domain($_domain)) continue; //只加载当前域名的路由器
			} else if (isset($serve['group'])) {
				$_group = $serve['group'];
			}
			if (isset($serve['dependencies'])) {
				foreach ($serve['dependencies'] as &$lib) {
					if (strpos($lib, '/') !== 0) $lib = $filepath . '/' . $lib;
				}
				$app->loader->import((array) $serve['dependencies']);
			}
			if (isset($serve['index'])) {
				$_index = $serve['index'];
				$_router = new Router('', $_domain, function() use ($_index, $filepath) {
					$result = require $filepath . '/' . $_index;
					if (is_string($result)) return $result; //判断返回值(排除 boolean 值，需 string 类型)
				});
			}
			if (isset($serve['router'])) {
				$_router = $serve['router'];
				$_router = call_user_func(function($app) use($_router, $filepath) {
						return require $filepath . '/' . $_router;
					}, $app);
				$_router->name = $_name;
				$_router->domain = $_domain;
				if (! $_router->default_route)
				$_router->default_route = $default_route ?: function() use ($app) {
						if (! headers_sent($file, $line)) {
							@header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
							@header('status: 404 Not Found');
							@header('Content-Type: text/html');
						}
						$app->event->trigger('notfound');
						return '<center><h1>404 Not Found</h1><hr><div>' . FADDLE_VERSION . '</div></center>';
					};
			}
			if (! isset($_router) or ! ($_router instanceof Router)) continue;
			if (isset($serve['chdir']) and strpos($serve['chdir'], '/') !== 0) {
					$serve['chdir'] = $filepath . '/' . $serve['chdir'];
			}
			if (isset($serve['chdir']) and is_dir($serve['chdir'])) {
				$_chdir = $serve['chdir']; //可以直接访问文件
				$_router->middleware(function()use ($_chdir) {
					chdir($_chdir);
					$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
					$uri = ltrim($uri, '/');
					if (is_file($uri)) {
						$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
						if ($ext == 'php') { //通过PHP处理文件
							ob_start() and ob_clean();
							require_once($uri);
							$body = ob_get_clean();
							if (! empty($body) and is_string($body)) {
								\Faddle\Http\Response::getInstance()->body($body);
								\Faddle\Http\Response::getInstance()->prepared = true;
							} else {
								
							}
						} else {
							//\Faddle\Http\Response::getInstance()->file($uri, null, null, 360, false);
							\Faddle\Common\Util\HttpUtils::remote_download($uri);
							\Faddle\App::getInstance()->abort();
						}
					}
				});
			}
			if (isset($serve['routes'])) {
				$_routes = $serve['routes'];
				foreach ($_routes as $p => $c) {
					if (is_file($filepath . '/' . $c)) { //先判断路由文件是否存在
						$_router->route($p, function() use ($c, $filepath) {
							$result = require $filepath . '/' . $c;
							if (is_string($result)) return $result;
						});
					} else if (is_string($c)) { //直接作为回调函数
						$_router->route($p, $c);
					} else trigger_error(sprintf('Route file not find: %s', $filepath . '/' . $c), E_USER_WARNING);
				}
			}
			$app->router->group($_group, $_router);
		}
		
		return true;
	}

	public static function check_domain($domain=null) {
		if (empty($domain)) return true;
		$server = $_SERVER['HTTP_HOST'] ?: $_SERVER['SERVER_NAME'];
		if (is_array($domain)) {
			foreach ($domain as $d) {
				if (strtolower($d) == strtolower($server)) return true;
			}
		} else {
			$domain = (string)$domain;
			if (preg_match($domain, $server, $arguments)) {
					return $arguments;
			}
			return stristr($server, $domain);
		}
		return false;
	}

}
