Faddle - a liberalization PHP Framework
=================================
Faddle Framework is just a PHP MVC system to give developers the creative experience of developing a web application. 
It's designed to be lightweight and modular, allowing developers to build better and easy to maintain&migrate code with PHP.

## Installation

It's recommended that you use [Composer][0] to install Faddle.
```bash
$ composer require autorealm/faddle "dev-master"
```
This will install Faddle and the only dependency:[PHP-PSR][1]. Faddle requires PHP 5.5.0 or newer.

## Usage

Create an ``index.php`` file with the following contents:
```php
<?php

require 'path/to/vendor/autoload.php';

$app = new Faddle\App();

$app->router->get('/hello/{name:str}', function ($name) {
    return 'Hello, ' . $name;
});

$app->run();
```
You may quickly test this using the built-in PHP server:
```bash
$ php -S localhost:8000
```
Going to http://localhost:8000/hello/world will now display "Hello, world".

---

### 如何配置

+ **PHP配置**

> 最开始着手的 PHP 程序都应当配置好的，以下是推荐的配置。

```php
ini_set('allow_url_include', true); //如果服务器不需要访问网络资源，就关闭它。
ini_set('memory_limit', '64M'); //内存限制，可以根据服务器内存进行相应配置。
ini_set('short_open_tag', true); //使用短标签，打开它写模板更方便
ini_set('output_buffering', true); //使用输出缓存，默认也是打开的，姑且在开一下。

date_default_timezone_set('PRC'); //时区为中国

if (defined('DEBUG') && DEBUG) {
    @set_time_limit(90);
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
}

```

+ **服务器配置**

  Faddle 推荐作为 MVC 模式框架，需要统一入口文件 ``index.php``。具体配置请参考相关服务器配置说明。
  
  **Apache** 的 ``.htaccess`` 配置示例：
```
<IfModule mod_rewrite.c>
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
</IfModule>
```

+ **应用配置**

  最重要的是配置应用的目录和类加载器。以下是示例。

```php

defined('APP_PATH') or define('APP_PATH', '/path/to/app');

defined('WEB_ROOT') or define('WEB_ROOT', $_SERVER['DOCUMENT_ROOT']);
defined('DS') or define('DS', DIRECTORY_SEPARATOR);

set_include_path(get_include_path() . PATH_SEPARATOR . APP_PATH);

```

+ **视图模板配置**

  可以自由选择PHP模板引擎如：**Smarty** 或者 **Twig**。以下是 **Faddle** 自带模板引擎的配置。

```php
$view_config = array(
        'suffix' => ['.view.php', '.faddle.php'], //模板文件后缀名（需要带上点号）
        'template_path' => WEB_ROOT . DS . 'templates', //模板文件夹位置（不能使用相对位置）
        'engine' => 'faddle', //模板引擎名称（这里无需变动）
        'static_cache' => false, //是否开启静态缓存（开启后将缓存经解析的静态内容）
        'storage_path' => 'views', //静态缓存输出路径（不能使用相对位置）
        'storage_expires' => 3600, //静态缓存过期时间（秒）
        'cache' => false, //是否开启动态缓存
        'cache_path' => 'views', //动态缓存路径（或作为 cache_driver 的 key 前缀）
        'cache_driver' => null, //缓存驱动器对象（未指定则使用文件缓存）
        'cache_expires' => 7200, //动态缓存过期时间（秒）
    );
```
> 可以单独写在一个独立配置文件中，只在使用 ``Faddle\View::make($tpl, $data, $config)`` 时引入即可。

+ **错误处理配置**

  在应用的事件流中，可以监听错误并进行处理。
```php
$app->on('error', function($err) use ($app) {
    if (! $err) return;
    if ($err instanceof \Exception or $err instanceof \Error) {
        $log = $err->getMessage() ."\n";
        $log .= '@file: ' . $err->getFile() . ' #' . $err->getLine() . '';
        write_log($log, get_class($err), LOGS_DIR, 'error-');
        if (DEBUG) echo \Faddle\Middleware\PrettyExceptions::renderBody($err);
    } else {
        write_log(print_r($err, true), get_class($err), LOGS_DIR, 'error-');
        if (DEBUG) var_dump($err);
    }
});
```

  默认会使用自带的错误处理方法，以下是自定义方法。
```php
set_exception_handler(function($e) {
    // handle the $e
});
```

+ **其他配置**

  数据库等配置这里不做介绍。

---

### 开始使用

#### APP

APP 是最先应该建立的对象，该对象仅作为容器，可以依赖并注入其他任何对象或者方法。

> APP 的相关代码应该写在单独的文件，如：``app.php``。

+ **新建 APP 对象**

> 可选参数：1.应用目录位置，2.配置文件路径

```php
$app = new Faddle\App(
    realpath(__DIR__.'/../'),
    false
);
```

+ **注入对象或者变量**

```php
$app->g('is_mobile', \Faddle\Common\Util\HttpUtils::is_mobile());
// 直接使用 $app->is_mobile; 即返回 boolean 值表示客户端是否是使用移动设备。
$app->logger = call_user_func(function() {
    $log_file = LOGS_DIR . '/app-debug.log';
    $new_log_name = LOGS_DIR . '/app-debug-' . date('Ymd', strtotime('-1 day')) . '.log';
    if (file_exists($log_file)) {
        $log_time = filemtime($log_file);
        if (date('d') != date('d', $log_time)) {
            rename($log_file, $new_log_name);
        }
    }
    return Faddle\Common\SimpleLogger::Filelog($log_file);
});
// 记录日志使用 $app->logger->debug('this is logger output.');

```

+ **注入方法函数**

  以下示例为注入视图模板方法并注册生成开始模板渲染事件。
```php
$app->register('init_view', function() use ($app) {
    static $view;
    if (isset($view)) return $view;
    $_config = include CONFIG_PATH . DS . 'view.php';
    $view = new \Faddle\View($_config);
    $app->g('view', $view);
    return $view;
});
$app->event->set('before_render');
$app->register('render', function($tpl, $data=array()) use ($app) {
    $view = $app->init_view();
    $app->event->fire('before_render', $view);
    return $view->show($tpl, $data);
});
```

+ **共享运行方法**

  表示在 ``$app->run()``时需要执行的方法。添加方法为 ``$app->share(Cloure)``，调用该方法即进入运行栈，栈顺序即表示运行时的加载顺序。
  
> 该方法推荐引入其他业务代码文件，因为是在闭包状态下运行的，里面定义的变量不会影响到全局环境。

  以下是示例。
```php
$app->share(function($app) {
    require __DIR__.'/routes.php';
});

$app->share(function($app) {
    if (file_exists(SERVES_PATH . '/routes.php')) {
        require SERVES_PATH . '/routes.php';
    } else if (file_exists(SERVES_PATH . '/serves.json')) {
        \Faddle\Router\ServesRouter::load(SERVES_PATH . '/serves.json');
    }
});
```

+ **事件处理**

  APP 默认有11个事件，分别为：``start``, ``before``, ``obtain``, ``present``, ``completed``, 
  
``error``, ``notfound``, ``badrequest``, ``unavailable``, ``next``, ``end``

  事件仅在 ``$app->run()`` 后产生。其中正常会触发的事件有**start**_(开始)_ **before**_(进入路由)_ **obtain**_(已匹配)_ **end**_(结束)_ 

  以下示例表示把要输出的内容转换为 JSON 格式
```php
$app->on('present', function($data) use ($app) {
        if (is_object($data)) $data = get_object_vars($data);
        $result = array(
            'result' => $data
            );
        
        $resp = array(
            'version' => '2.0'
        );
        $resp = array_merge($resp, $result);
        //header('Content-Type: application/json');
        
        return json_encode($resp, JSON_UNESCAPED_UNICODE);
    });
```

#### Route _路由_

**主路由器**

默认主路由是 APP 注入的 router 对象。
```php
$router = $app->router;
$router->get('/post/{:int}', 'BlogController@post');
```
路由的路径参数说明：

1. /path1/path2：全匹配模式，只有 Uri 的 Path 完全一样才匹配。

2. /post/{id:int}：通用匹配模式，其中``id``为返回给回调的参数名称，``int``表示类型，只有是数字时才匹配。

3. /post/(?list|list.json)：正则匹配模式，具体可参考正则表达式规则。

路由的回调处理函数（或者称为 Controller 控制器的 Action 动作），返回值说明：

1. 返回内容（非 null, boolean），表示在回应中输出该内容。

2. 返回 null 或没有返回任何内容，则表示不在回应中做输出处理。

3. 返回 true，表示回应已做处理，可以输出。

4. 返回 false，则表示触发服务不可用错误。

**单路由**

可以通过 ``faddle_route()``快速建立一个单路由，再通过 ``$router->set($route)`` 配置到路由器中。
以下是建立单路由的参数说明：

1. 请求方法，get 或者 post 也可以用数组包含多个。

2. 路径，见上方说明。

3. 回调函数，可以是任何 callable 类型。或者使用 ``Controller@Action`` 这种方式。 

4. [可选]名称，表示这个路由的名称，以后可以进行识别。

5. [可选]命名空间，当使用的控制器包含命名空间时，可以在这里定义。

> 路由器 Router 的 ``get`` ``post`` ``put`` 等方法返回的也是单路由 Route 对象。

**子路由**

任何路由器对象都可以通过 ``group`` 方法包含子路由器对象（称为蓝图）。

**服务路由**

可以快速配置多域名路由或多个蓝图。


#### Middleware _中间件_

中间件即为传递请求时需经过的业务处理方法。可在全局路由，单路由，或者匹配路径中进行设置。


#### View _视图_

视图可通过 ``extend``方法进行扩展。

#### ViewEngine _视图引擎_

Faddle 有自带的视图模板引擎。

---

### 进阶使用

详见文档。

---

## Documentation

+ Read the [wiki documentation][2]

## License

The project is developed by [KYO][3] and distributed under the [MIT LICENSE][4]

## Thanks

All Github Open-source Contributor!

[0]: https://getcomposer.org/
[1]: http://www.php-fig.org/psr/
[2]: https://github.com/autorealm/faddle/wiki
[3]: http://advos.cn
[4]: https://github.com/autorealm/faddle/raw/master/LICENSE
