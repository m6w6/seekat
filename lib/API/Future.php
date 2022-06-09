<?php

namespace seekat\API;

interface Future {
	/**
	 * @param callable $onCancel
	 * @return object Promisor providing a promise() method
	 */
	function createContext(callable $onCancel = null) : object;

	/**
	 * @return object promise
	 */
	function getPromise(object $context) : object;

	function isPromise(object $promise) : bool;

	function cancelPromise(object $promise) : void;

	/**
	 * @return object promise
	 */
	function handlePromise(object $promise, callable $onResult = null, callable $onError = null) : object;

	/**
	 * Create an immediately resolved promise
	 */
	function resolve(mixed $value) : object;

	/**
	 * @param object $context Promisor returned by createContext
	 */
	function resolver(object $context) : \Closure;

	/**
	 * Create an immediately rejected promise
	 */
	function reject(mixed $reason) : object;

	/**
	 * @param object $context Promisor returned by createContext
	 */
	function rejecter(object $context) : \Closure;

	/**
	 * @param array $promises
	 * @return object promise
	 */
	function all(array $promises) : object;

	/**
	 *
	 */
	function reducer() : \Closure;
}
