<?php

namespace seekat\API;

use Generator;
use http\Client;
use React\Promise\{
	Deferred,
	ExtendedPromiseInterface,
	PromiseInterface,
	function all
};

class Invoker extends Deferred
{
	/**
	 * The HTTP client
	 * @var Client
	 */
	private $client;

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
	 * Create a new generator invoker
	 * @param Client $client
	 */
	function __construct(Client $client) {
		$this->client = $client;

		parent::__construct(function($resolve, $reject) {
			return $this->cancel($resolve, $reject);
		});
	}

	/**
	 * Invoke $generator to create a \Generator which yields promises
	 *
	 * @param callable $generator as function():\Generator, creating a generator yielding promises
	 * @return Invoker
	 */
	function invoke(callable $generator) : Invoker {
		$this->iterate($generator());
		return $this;
	}

	/**
	 * Iterate over $gen, a \Generator yielding promises
	 *
	 * @param Generator $gen
	 * @return Invoker
	 */
	function iterate(Generator $gen) : Invoker {
		$this->cancelled = false;

		foreach ($gen as $promise) {
			if ($this->cancelled) {
				break;
			}
			$this->give($promise, $gen);
		}

		if (!$this->cancelled) {
			$this->resolve($this->result = $gen->getReturn());
		}
		return $this;
	}

	/**
	 * Get the generator's result
	 *
	 * @return ExtendedPromiseInterface
	 */
	function result() : ExtendedPromiseInterface {
		return $this->promise();
	}

	/**
	 * Promise handler
	 *
	 * @param array|PromiseInterface $promise
	 * @param Generator $gen
	 */
	private function give($promise, Generator $gen) {
		if ($promise instanceof PromiseInterface) {
			$promise->then(function($result) use($gen) {
				if (($promise = $gen->send($result))) {
					$this->give($promise, $gen);
				}
			});
		} else {
			all($promise)->then(function($results) use($gen) {
				if (($promise = $gen->send($results))) {
					$this->give($promise, $gen);
				}
			});
		}
		$this->client->send();
	}

	/**
	 * Cancellation callback
	 *
	 * @param callable $resolve
	 * @param callable $reject
	 */
	private function cancel(callable $resolve, callable $reject) {
		$this->cancelled = true;

		/* did we finish in the meantime? */
		if ($this->result) {
			$resolve($this->result);
		} else {
			$reject("Cancelled");
		}
	}
}
