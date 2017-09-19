<?php

namespace seekat\API;

use AsyncInterop\Promise;
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
	 * @var Promise
	 */
	private $promise;

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

		$context = $future->createContext(function() {
			$this->cancelled = true;
		});
		$this->promise = $future->getPromise($context);
		$this->resolve = API\Future\resolver($future, $context);
		$this->reject = API\Future\rejecter($future, $context);
		$this->reduce = API\Future\reducer($future, $context);
	}

	/**
	 * Iterate over $gen, a \Generator yielding promises
	 *
	 * @param Generator $gen
	 * @return Promise
	 */
	function __invoke(Generator $gen) : Promise {
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

		return $this->promise;
	}

	/**
	 * Promise handler
	 *
	 * @param array|Promise $promise
	 * @param Generator $gen
	 */
	private function give($promise, Generator $gen) {
		if ($promise instanceof \Traversable) {
			$promise = iterator_to_array($promise);
		}
		if (is_array($promise)) {
			$promise = ($this->reduce)($promise);
		}
		if ($promise instanceof Promise) {
			$promise->when(function($error, $result) use($gen) {
				if ($error) {
					$gen->throw(exception($error));
				}
				if (($promise = $gen->send($result))) {
					$this->give($promise, $gen);
				}
			});
		} else {
			$gen->throw(new UnexpectedValueException(
				"Expected Promise or array of Promises; got ".\seekat\typeof($promise)));
		}
		/* FIXME: external loop */
		($this->loop)();
	}
}
