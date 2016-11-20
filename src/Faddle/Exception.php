<?php namespace Faddle;

/**
 * 异常基类
 */
class Exception extends \Exception {

    public function __construct($message, $code=0) {
        $this->message = $message;
        $this->code = $code;
    }

}

