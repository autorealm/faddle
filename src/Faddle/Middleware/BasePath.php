<?php namespace Faddle\Middleware;

use Faddle\Middleware\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Middleware to strip the path prefix.
 */
class BasePath implements MiddlewareInterface {

    use Utils\BasePathTrait;

    /**
     * Constructor. Set the path prefix.
     *
     * @param string|null $basePath
     */
    public function __construct($basePath = null)
    {
        if ($basePath !== null) {
            $this->basePath($basePath);
        }
    }

    /**
     * Execute the middleware.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param callable          $next
     *
     * @return ResponseInterface
     */
    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next=null)
    {
        $uri = $request->getUri();
        $path = $this->getBasePath($uri->getPath());
        $request = $request->withUri($uri->withPath($path));

        return $next($request, $response);
    }
}
