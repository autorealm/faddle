<?php namespace Faddle\Middleware;

use Faddle\App;
use Faddle\Http\Request as Request;
use Faddle\Http\Response as Response;

/**
 * Content Types Middleware
 */
class PageCacheMiddleware extends BaseMiddleware {

	protected $injectors = array(
		'domain'  => false,
		'prefix'  => 'page::',
		'key'     => false,
		'cache'   => false,
		'hash'    => true,
		'timeout' => 0
	);

	public function __construct($settings = array()) {
		$this->injectors = array_merge($injectors, $settings);
	}

	public function __invoke(Request $request, Response $response, callable $out=null) {
		// Check cache provider and input method
		if (!$this->injectors['cache'] || !$this->input->is("get")) {
			return;
		}
		
		if ($this->injectors['domain']) {
			// If generate key with domain
			$key = $this->input->url();
		} elseif ($this->injectors['key']) {
			// Support create key dynamic
			if ($this->injectors['key'] instanceof \Closure) {
				$key = call_user_func($this->injectors['key'], $this->input);
			} else {
				$key = $this->injectors['key'];
			}
		} else {
			// Create key only with uri
			$key = $this->input->uri();
		}
		
		if ($this->injectors['hash']) {
			// Hash the key
			$key = sha1($this->injectors['hash']);
		}
		
		// Add prefix for the key
		$key = $this->injectors['prefix'] . $key;
		
		/** @var $cache \Pagon\Cache */
		$cache = $this->injectors['cache'];
		
		if ($page = $cache->get($key)) {
			// Try to get the page cacheF
			$page = json_decode($page, true);
			$this->output->header($page['header']);
			$this->output->display($page['body']);
			return;
		}
		
		$timeout = $this->injectors['timeout'];
		$output = &$this->output;
		$this->app->on('completed', function() use ($key, $cache, $timeout, &$output) {
			$page = array();
			$page['header'] = $output->header();
			$page['body'] = $output->body();
			
			// Save data to cache
			$cache->set($key, json_encode($page), $timeout);
		});
		
	}

}