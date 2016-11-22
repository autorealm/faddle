<?php namespace Faddle\Support;

use InvalidArgumentException;

/**
 * 管道类
 */
class Pipeline {

	private $pipes;
	private $processor;
	private $isFirst = false;

	/**
	 * Pipeline Constructor
	 */
	public function __construct(array $pipes=[], PipeProcessorInterface $processor=null) {
		foreach ($pipes as $pipe) {
			if (false === is_callable($pipe) throw new InvalidArgumentException('All pipe should be callable.');
		}
		$this->pipes = $pipes;
		$this->processor = $processor ?: new InterruptibleProcessor();
	}

	/**
	 * Create a new pipeline with an appended stage.
	 *
	 * @param callable $operation
	 * @return static
	 */
	private function pipe(callable $pipe) {
		$pipeline->pipes[] = $pipe;
		return $pipeline;
	}

	public function process($payload) {
		return $this->processor->process($this->pipes, $payload);
	}

	public function __invoke($payload) {
		return $this->process($payload);
	}

}

class PipelineBuilder {

	private $pipes = [];

	public function __construct() {
		
	}

	/**
	 * Add an stage.
	 *
	 * @param callable $stage
	 * @return $this
	 */
	public function append(callable $pipe) {
		$this->pipes[] = $pipe;
		return $this;
	}

	/**
	 * Build a new Pipeline object
	 *
	 * @param  ProcessorInterface|null $processor
	 * @return PipelineInterface
	 */
	public function build(PipeProcessorInterface $processor=null) {
		return new Pipeline($this->pipes, $processor);
	}


}

interface PipeProcessorInterface {

	/**
	 * @param array $pipes
	 * @param mixed $payload
	 *
	 * @return mixed
	 */
	public function process(array $pipes, $payload);
	
}

class InterruptibleProcessor implements PipeProcessorInterface {

	/**
	 * @var callable
	 */
	private $check;

	/**
	 * InterruptibleProcessor constructor.
	 *
	 * @param callable $check
	 */
	public function __construct(callable $check=null) {
		$this->check = $check;
	}

	/**
	 * @param array $pipes
	 * @param mixed $payload
	 * @return mixed
	 */
	public function process(array $pipes, $payload) {
		foreach ($pipes as $pipe) {
			$payload = call_user_func($pipe, $payload);
			if ($this->check and true !== call_user_func($this->check, $payload)) {
				return $payload;
			}
		}
		
		return $payload;
	}

}