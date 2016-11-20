<?php namespace Faddle\Support;

use Faddle\App;
use Faddle\Faddle;
use Faddle\ServiceProviderInterface;
use Faddle\Http\Response;
use Faddle\Http\HttpStatus;

class JsonRpcServiceProvider implements ServiceProviderInterface {

	public function register(Faddle $app) {
		$this->boot($app);
		//$app->jsonrpc = new Struct();
	}

	public function boot(Faddle $app) {
		$app->on('present', function($data) use ($app) {
			//$request = json_decode(file_get_contents('php://input'), true);
			$code = Response::getInstance()->status;
			if (! $data) {
				$result = array(
					'error' => array(
						'code' => $code,
						'message' => HttpStatus::getMessageFromCode($code)
					));
			} else if (is_string($data) and ! is_null(json_decode($data))) {
				$result = json_decode($data, true);
			} else {
				if (is_object($data)) $data = get_object_vars($data);
				
				$result = array(
					'result' => $data
					);
			}
			
			$response = array(
				'jsonrpc' => '2.0',
				'code' => $code
			);
			
			$response = array_merge($response, $result);
			Response::getInstance()->content_type = 'application/json';
			
			return json_encode($response, JSON_UNESCAPED_UNICODE);
		});
	}

}
