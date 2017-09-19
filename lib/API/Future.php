<?php

namespace seekat\API;

interface Future
{
	/**
	 * @param callable $onCancel
	 * @return mixed Promisor providing a promise() method
	 */
	function createContext(callable $onCancel = null);

	/**
	 * @param object $context Promisor
	 * @return mixed promise
	 */
	function getPromise($context);

	/**
	 * @param mixed $promise
	 * @return bool
	 */
	function isPromise($promise) : bool;

	/**
	 * @param mixed $promise
	 * @return bool
	 */
	function cancelPromise($promise) : bool;

	/**
	 * @param mixed $promise
	 * @param callable|null $onResult
	 * @param callable|null $onError
	 * @return mixed promise
	 */
	function handlePromise($promise, callable $onResult = null, callable $onError = null);

	/**
	 * @param object $context Promisor returned by createContext
	 * @param mixed $value
	 * @return void
	 */
	function resolve($context, $value);

	/**
	 * @param object $context Promisor returned by createContext
	 * @param mixed $reason
	 * @return void
	 */
	function reject($context, $reason);

	/**
	 * @param object $context Promisor returned by createContext
	 * @param array $promises
	 * @return mixed promise
	 */
	function all($context, array $promises);
}
