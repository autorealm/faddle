<?php namespace Faddle\Http\Exception;

use BadMethodCallException;

/**
 * Exception indicating a deprecated method.
 */
class DeprecatedMethodException extends BadMethodCallException implements ExceptionInterface {

}
