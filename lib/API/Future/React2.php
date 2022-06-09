<?php

namespace seekat\API\Future;

use seekat\API\Future;
use React;

final class React2 extends Common implements Future {
	protected string $promiseType = React\Promise\Promise::class;

	function createContext(callable $onCancel = null) : React\Promise\PromisorInterface {
		$context = new React\Promise\Deferred($onCancel);
		if (isset($onCancel)) {
			$this->cancellations[$context->promise()] = $context->promise()->cancel(...);
		}
		return $context;
	}

	function handlePromise(object $promise, callable $onResult = null, callable $onError = null) : React\Promise\PromiseInterface {
		return $promise->then($onResult, $onError);
	}

	function reject(mixed $reason) : object {
		$context = $this->createContext();
		$context->reject($reason);
		return $context->promise();
	}

	function rejecter(object $context) : \Closure {
		return function($reason) use($context) {
			$context->reject($reason);
		};
	}

	/**
	 * @param array<React\Promise\PromiseInterface> $promises
	 * @return React\Promise\PromiseInterface
	 */
	function all(array $promises) : React\Promise\PromiseInterface {
		return React\Promise\all($promises);
	}
}
