<?php

namespace seekat\API\Future;

use Amp\Deferred as AmpDeferred;
use Amp\Promise as AmpPromise;
use React\Promise\Deferred as ReactDeferred;
use React\Promise\PromiseInterface as ReactPromise;
use seekat\API\Future;

/**
 * @param Future $future
 * @param mixed $value
 * @return mixed promise
 */
function resolve(Future $future, $value) {
	$promisor = $future->createContext();
	$future->resolve($promisor, $value);
	return $future->getPromise($promisor);
}

/**
 * @param Future $future
 * @param mixed $reason
 * @return mixed promise
 */
function reject(Future $future, $reason) {
	$promisor = $future->createContext();
	$future->reject($promisor, $reason);
	return $future->getPromise($promisor);
}

/**
 * @param Future $future
 * @param mixed $context Promisor
 * @return \Closure
 */
function resolver(Future $future, $context) {
	return function($value) use($future, $context) {
		return $future->resolve($context, $value);
	};
}

/**
 * @param Future $future
 * @param mixed $context Promisor
 * @return \Closure
 */
function rejecter(Future $future, $context) {
	return function($reason) use($future, $context) {
		return $future->reject($context, $reason);
	};
}

/**
 * @param Future $future
 * @param mixed $context Promisor
 * @return \Closure
 */
function reducer(Future $future, $context) {
	return function(array $promises) use($future, $context) {
		return $future->all($context, $promises);
	};
}

/**
 * @return Future
 */
function react() {
	return new class implements Future {
		/**
		 * @param callable|null $onCancel
		 * @return ReactDeferred
		 */
		function createContext(callable $onCancel = null) {
			return new ReactDeferred($onCancel);
		}

		function getPromise($context) {
			/* @var $context ReactDeferred */
			return $context->promise();
		}

		function isPromise($promise) : bool {
			return $promise instanceof ReactPromise;
		}

		function handlePromise($promise, callable $onResult = null, callable $onError = null) {
			return $promise->then($onResult, $onError);
		}

		function cancelPromise($promise) : bool {
			/* @var $promise \React\Promise\Promise */
			$promise->cancel();
			return true;
		}

		function resolve($context, $value) {
			/* @var $context ReactDeferred */
			$context->resolve($value);
		}

		function reject($context, $reason) {
			/* @var $context ReactDeferred */
			$context->reject($reason);
		}

		function all($context, array $promises) {
			return \React\Promise\all($promises);
		}
	};
}

/**
 * @return Future
 */
function amp() {
	return new class implements Future {
		/**
		 * @return AmpDeferred
		 */
		function createContext(callable $onCancel = null) {
			return new AmpDeferred();
		}

		function getPromise($context) {
			/* @var $context AmpDeferred */
			return $context->promise();
		}

		function isPromise($promise) : bool {
			return $promise instanceof AmpPromise;
		}

		function handlePromise($promise, callable $onResult = null, callable $onError = null) {
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

		function cancelPromise($promise) : bool {
			return false;
		}

		function resolve($context, $value) {
			/* @var $context AmpDeferred */
			$context->resolve($value);
		}

		function reject($context, $reason) {
			/* @var $context AmpDeferred */
			$context->fail(\seekat\Exception\exception($reason));
		}

		function all($context, array $promises) {
			return \Amp\all($promises);
		}
	};
}
