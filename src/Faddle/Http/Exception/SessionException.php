<?php namespace Faddle\Http\Exception;

class SessionException extends \Exception implements ExceptionInterface {

	const SESSION_CORRUPT = 1;

}
