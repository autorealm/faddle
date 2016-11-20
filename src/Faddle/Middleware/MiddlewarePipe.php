<?php namespace Faddle\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SplQueue;

/**
 * Pipe middleware like unix pipes.
 *
 * This class implements a pipe-line of middleware, which can be attached using
 * the `pipe()` method, and is itself middleware.
 *
 * The request and response objects are decorated using the Zend\Stratigility\Http
 * variants in this package, ensuring that the request may store arbitrary
 * properties, and the response exposes the convenience `write()`, `end()`, and
 * `isComplete()` methods.
 *
 * It creates an instance of `Next` internally, invoking it with the provided
 * request and response instances; if no `$out` argument is provided, it will
 * create a `FinalHandler` instance and pass that to `Next` as well.
 *
 * Inspired by Sencha Connect.
 *
 * @see https://github.com/sencha/connect
 */
class MiddlewarePipe implements MiddlewareInterface {

	/**
	 * @var SplQueue
	 */
	protected $pipeline;

	/**
	 * Constructor
	 *
	 * Initializes the queue.
	 */
	public function __construct() {
		$this->pipeline = new SplQueue();
	}

	/**
	 * Handle a request
	 *
	 * Takes the pipeline, creates a Next handler, and delegates to the
	 * Next handler.
	 *
	 * If $done is a callable, it is used as the "final handler" when
	 * $next has exhausted the pipeline; otherwise, a FinalHandler instance
	 * is created and passed to $next during initialization.
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param callable $done
	 * @return Response
	 */
	public function __invoke(Request $request, Response $response, callable $done = null) {
		
		if ($this->pipeline->isEmpty()) {
			if (is_callable($done)) return $done($request, $response);
			else return $response;
		}
		
		$layer = $this->pipeline->dequeue();
		$path = $request->getUri()->getPath() ?: '/';
		$handler = array_pop($layer);
		$route = array_pop($layer);
		$route = (strlen($route) > 1) ? rtrim($route, '/') : $route;
		
		// Skip if layer path does not match current url
		if (substr(strtolower($path), 0, strlen($route)) !== strtolower($route)) {
			return $this($request, $response, $done);
		}
		// Skip if match is not at a border ('/', '.', or end)
		$border = (strlen($path) > strlen($route)) ? $path[strlen($route)] : '';
		$border = ($route === '/') ? '/' : $border;
		if ($border && '/' !== $border && '.' !== $border) {
			return $this($request, $response, $done);
		}
		try {
			$result = call_user_func($handler, $request, $response, $this);
		} catch (\Exception $e) {}
		if ($result instanceof Response) return $result;
		
		return $this($request, $response, $done);
	}

	/**
	 * Attach middleware to the pipeline.
	 *
	 * Each middleware can be associated with a particular path; if that
	 * path is matched when that middleware is invoked, it will be processed;
	 * otherwise it is skipped.
	 *
	 * No path means it should be executed every request cycle.
	 *
	 * A handler CAN implement MiddlewareInterface, but MUST be callable.
	 *
	 * Handlers with arity >= 4 or those implementing ErrorMiddlewareInterface
	 * are considered error handlers, and will be executed when a handler calls
	 * $next with an error or raises an exception.
	 *
	 * @see MiddlewareInterface
	 * @see ErrorMiddlewareInterface
	 * @see Next
	 * @param string|callable|object $path Either a URI path prefix, or middleware.
	 * @param null|callable|object $middleware Middleware
	 * @return self
	 */
	public function pipe($path, $middleware = null) {
		if (null === $middleware && is_callable($path)) {
			$middleware = $path;
			$path       = '/';
		}
		
		// Ensure we have a valid handler
		if (! is_callable($middleware)) {
			throw new InvalidArgumentException('Middleware must be callable');
		}
		
		$this->pipeline->enqueue(array(
			$this->normalizePipePath($path),
			$middleware
		));
		
		// @todo Trigger event here with route details?
		return $this;
	}

	/**
	 * Normalize a path used when defining a pipe
	 *
	 * Strips trailing slashes, and prepends a slash.
	 *
	 * @param string $path
	 * @return string
	 */
	private function normalizePipePath($path) {
		// Prepend slash if missing
		if (empty($path) || $path[0] !== '/') {
			$path = '/' . $path;
		}
		
		// Trim trailing slash if present
		if (strlen($path) > 1 && '/' === substr($path, -1)) {
			$path = rtrim($path, '/');
		}
		
		return $path;
	}

}
