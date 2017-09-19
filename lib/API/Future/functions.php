<?php

namespace seekat\API\Future;

use Amp\Deferred as AmpDeferred;
use AsyncInterop\Promise;
use React\Promise\Deferred as ReactDeferred;
use seekat\API\Future;

/**
 * @param Future $future
 * @param mixed $value
 * @return Promise
 */
function resolve(Future $future, $value) {
	$promisor = $future->createContext();
	$future->onSuccess($promisor, $value);
	return $future->getPromise($promisor);
}

/**
 * @param Future $future
 * @param mixed $reason
 * @return Promise
 */
function reject(Future $future, $reason) {
	$promisor = $future->createContext();
	$future->onFailure($promisor, $reason);
	return $future->getPromise($promisor);
}

/**
 * @param Future $future
 * @param mixed $context Promisor
 * @return \Closure
 */
function resolver(Future $future, $context) {
	return function($value) use($future, $context) {
		return $future->onSuccess($context, $value);
	};
}

/**
 * @param Future $future
 * @param mixed $context Promisor
 * @return \Closure
 */
function rejecter(Future $future, $context) {
	return function($reason) use($future, $context) {
		return $future->onFailure($context, $reason);
	};
}

/**
 * @param Future $future
 * @param mixed $context Promisor
 * @return \Closure
 */
function reducer(Future $future, $context) {
	return function(array $promises) use($future, $context) : Promise {
		return $future->onMultiple($context, $promises);
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

		function getPromise($context) : Promise {
			/* @var $context ReactDeferred */
			return $context->promise();
		}

		function cancelPromise(Promise $promise) : bool {
			/* @var $promise \React\Promise\Promise */
			$promise->cancel();
			return true;
		}

		function onSuccess($context, $value) {
			/* @var $context ReactDeferred */
			$context->resolve($value);
		}

		function onFailure($context, $reason) {
			/* @var $context ReactDeferred */
			$context->reject($reason);
		}

		function onMultiple($context, array $promises) : Promise {
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

		function getPromise($context) : Promise {
			/* @var $context AmpDeferred */
			return $context->promise();
		}

		function cancelPromise(Promise $promise) : bool {
			return false;
		}

		function onSuccess($context, $value) {
			/* @var $context AmpDeferred */
			$context->resolve($value);
		}

		function onFailure($context, $reason) {
			/* @var $context AmpDeferred */
			$context->fail(\seekat\Exception\exception($reason));
		}

		function onMultiple($context, array $promises) : Promise {
			return \Amp\all($promises);
		}
	};
}
