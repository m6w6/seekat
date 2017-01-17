<?php

namespace seekat\API\Future;

use Amp\Deferred as AmpDeferred;
use AsyncInterop\Promise;
use Icicle\Awaitable\Deferred as IcicleDeferred;
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
function updater(Future $future, $context) {
	return function($update) use($future, $context) {
		return $future->onUpdate($context, $update);
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

		function onSuccess($context, $value) {
			/* @var $context ReactDeferred */
			$context->resolve($value);
		}

		function onFailure($context, $reason) {
			/* @var $context ReactDeferred */
			$context->reject($reason);
		}

		function onUpdate($context, $update) {
			/* @var $context ReactDeferred */
			$context->notify($update);
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

		function onSuccess($context, $value) {
			/* @var $context AmpDeferred */
			$context->resolve($value);
		}

		function onFailure($context, $reason) {
			/* @var $context AmpDeferred */
			$context->fail($reason);
		}

		function onUpdate($context, $update) {
			/* @var $context AmpDeferred */
			/* noop */
		}
	};
}

/**
 * @return Future
 */
function icicle() {
	return new class implements Future {
		/**
		 * @param callable|null $onCancel
		 * @return IcicleDeferred
		 */
		function createContext(callable $onCancel = null) {
			return new IcicleDeferred($onCancel);
		}

		function getPromise($context): Promise {
			/* @var $context IcicleDeferred */
			return $context->getPromise();
		}

		function onSuccess($context, $value) {
			/* @var $context IcicleDeferred */
			$context->resolve($value);
		}

		function onFailure($context, $reason) {
			/* @var $context IcicleDeferred */
			$context->reject($reason);
		}

		function onUpdate($context, $update) {
			/* @var $context IcicleDeferred */
			/* noop */
		}
	};
}
