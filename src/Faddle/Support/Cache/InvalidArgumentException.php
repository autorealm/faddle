<?php namespace Faddle\Support\Cache;

use Exception;

class InvalidArgumentException extends Exception implements \Psr\Cache\InvalidArgumentException
{
}
