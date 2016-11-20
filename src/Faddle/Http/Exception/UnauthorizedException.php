<?php namespace Faddle\Http\Exception;

class UnauthorizedException extends \Exception implements ExceptionInterface {

	protected $code = 401;
	protected $message = 'The request requires user authentication';

}