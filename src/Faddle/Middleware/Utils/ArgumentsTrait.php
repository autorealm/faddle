<?php namespace Faddle\Middleware\Utils;

/**
 * Trait used by all middlewares with arguments() option.
 */
trait ArgumentsTrait
{
    private $arguments = [];

    /**
     * Extra arguments passed to the controller.
     *
     * @return self
     */
    public function arguments()
    {
        $this->arguments = func_get_args();

        return $this;
    }
}
