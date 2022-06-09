<?php

namespace seekat\API\Future;

use Amp;
use seekat\API\Future;
use function seekat\Exception\exception;

final class Amp2 extends Common implements Future {
	protected string $promiseType = Amp\Promise::class;

	function createContext(callable $onCancel = null) : Amp\Deferred {
		$context = new Amp\Deferred();
		if (isset($onCancel)) {
			$this->cancellations[$context->promise()] = $onCancel;
		}
		return $context;
	}

	function handlePromise(object $promise, callable $onResult = null, callable $onError = null) : Amp\Promise {
		$promise->onResolve(function($error = null, $result = null) use($onResult, $onError) {
			if ($error) {
				if ($onError) {
					$onError($error);
				}
			} else {
				if ($onResult) {
					$onResult($result);
				}
			}
		});
		return $promise;
	}

	function reject(mixed $reason) : object {
		$this->createContext()->fail(\seekat\Exception\exception($reason));
	}

	function rejecter(object $context) : \Closure {
		return function($reason) use($context) {
			$context->fail(exception($reason));
		};
	}

	/**
	 * @param array<Amp\Promise> $promises
	 */
	function all(array $promises) : Amp\Promise {
		return Amp\Promise\all($promises);
	}
}
