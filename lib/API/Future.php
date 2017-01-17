<?php

namespace seekat\API;

use AsyncInterop\Promise;

interface Future
{
	/**
	 * @param callable $onCancel
	 * @return mixed Promisor providing a promise() method
	 */
	function createContext(callable $onCancel = null);

	/**
	 * @param object $context Promisor
	 * @return Promise
	 */
	function getPromise($context) : Promise;

	/**
	 * @param object $context Promisor returned by createContext
	 * @param mixed $value
	 * @return void
	 */
	function onSuccess($context, $value);

	/**
	 * @param object $context Proisor returned by createContext
	 * @param mixed $reason
	 * @return void
	 */
	function onFailure($context, $reason);

	/**
	 * @param object $context Promisor returned by createContext
	 * @param mixed $update
	 * @return void
	 */
	function onUpdate($context, $update);
}
