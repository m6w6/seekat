<?php

namespace seekat\API;

use Exception;
use Generator;
use seekat\API;
use seekat\Exception\{function exception};

final class Consumer {
	/**
	 * The return value of the generator
	 * @var mixed
	 */
	private mixed $result = null;

	/**
	 * Cancellation flag
	 */
	private bool $cancelled = false;

	/**
	 * Promise
	 */
	private object $promise;

	private \Closure $resolve;
	private \Closure $reject;
	private \Closure $reduce;

	/**
	 * Create a new generator consumer
	 */
	function __construct(private readonly Future $future, private readonly \Closure $loop) {
		$context = $future->createContext(function() {
			$this->cancelled = true;
		});
		$this->promise = $future->getPromise($context);
		$this->resolve = $future->resolver($context);
		$this->reject = $future->rejecter($context);
		$this->reduce = $future->reducer();
	}

	/**
	 * Iterate over $gen, a \Generator yielding promises
	 *
	 * @param Generator $gen
	 * @return mixed promise
	 */
	function __invoke(Generator $gen) : mixed {
		$this->cancelled = false;
		foreach ($gen as $promise) {
			if ($this->cancelled) {
				break;
			}
			$this->give($promise, $gen);
		}

		if ($this->cancelled) {
			($this->reject)("Cancelled");
		} else if (!$gen->valid()) {
			try {
				$this->result = $gen->getReturn();
			} catch (Exception $e) {
				assert($e->getMessage() === "Cannot get return value of a generator that hasn't returned");
			}
		}
		($this->resolve)($this->result);

		return $this->promise;
	}

	/**
	 * Promise handler
	 *
	 * @param mixed $promise
	 * @param Generator $gen
	 */
	private function give(mixed $promise, Generator $gen) : void {
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
