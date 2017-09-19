<?php

namespace seekat\API;

use Generator;
use http\Client;
use seekat\API;
use seekat\Exception\{
	InvalidArgumentException, UnexpectedValueException, function exception
};

final class Consumer
{
	/**
	 * Loop
	 * @var callable
	 */
	private $loop;

	/**
	 * The return value of the generator
	 * @var mixed
	 */
	private $result;

	/**
	 * Cancellation flag
	 * @var bool
	 */
	private $cancelled = false;

	/**
	 * @var Future
	 */
	private $future;

	/**
	 * @var mixed
	 */
	private $context;

	/**
	 * @var \Closure
	 */
	private $resolve;

	/**
	 * @var \Closure
	 */
	private $reject;

	/**
	 * @var \Closure
	 */
	private $reduce;

	/**
	 * Create a new generator consumer
	 * @param Future $future
	 * @param callable $loop
	 */
	function __construct(Future $future, callable $loop) {
		$this->loop = $loop;

		$this->future = $future;
		$this->context = $future->createContext(function() {
			$this->cancelled = true;
		});
		$this->resolve = API\Future\resolver($future, $this->context);
		$this->reject = API\Future\rejecter($future, $this->context);
		$this->reduce = API\Future\reducer($future, $this->context);
	}

	/**
	 * Iterate over $gen, a \Generator yielding promises
	 *
	 * @param Generator $gen
	 * @return mixed promise
	 */
	function __invoke(Generator $gen) {
		$this->cancelled = false;

		foreach ($gen as $promise) {
			if ($this->cancelled) {
				break;
			}
			$this->give($promise, $gen);
		}

		#($this->loop)();

		if (!$this->cancelled) {
			$this->result = $gen->getReturn();
		}
		if (isset($this->result)) {
			($this->resolve)($this->result);
		} else {
			($this->reject)("Cancelled");
		}

		return $this->context->promise();
	}

	/**
	 * Promise handler
	 *
	 * @param mixed $promise
	 * @param Generator $gen
	 */
	private function give($promise, Generator $gen) {
		if ($promise instanceof \Traversable) {
			$promise = iterator_to_array($promise);
		}
		if (is_array($promise)) {
			$promise = ($this->reduce)($promise);
		}

		$this->future->handlePromise($promise, function($result) use($gen) {
			if (($promise = $gen->send($result))) {
				$this->give($promise, $gen);
			}
		}, function($error) use($gen) {
			$gen->throw(exception($error));
		});

		/* FIXME: external loop */
		($this->loop)();
	}
}
