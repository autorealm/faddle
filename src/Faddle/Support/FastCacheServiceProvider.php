<?php namespace Faddle\Support;

use Faddle\App;
use Faddle\Faddle;
use Faddle\ServiceProviderInterface;
use Faddle\Storage\StaticCache;

class FastCacheServiceProvider implements ServiceProviderInterface {

	public function register(Faddle $app) {
		$app->fastcache = $app->share(function() use ($app) {
			return new StaticCache(
						$app->config('cache.path'),
						$app->config('cache.extension')
			]);
		});
	}

	public function boot(Faddle $app) {
		
	}

}
