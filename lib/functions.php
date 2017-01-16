<?php

namespace seekat;

/**
 * Generate a human readable represenation of a variable
 * @param mixed $arg
 * @param bool $export whether to var_export the $arg
 * @return string
 */
function typeof($arg, $export = false) {
	$type = (is_object($arg)
		? "instance of ".get_class($arg)
		: gettype($arg)
	);
	if ($export) {
		$type .= ": ".var_export($arg, true);
	}
	return $type;
}

namespace seekat\Exception;

/**
 * Canonicalize an error message from a string or Exception
 * @param string|Exception $error
 * @return string
 */
function message(&$error) : string {
	if ($error instanceof \Throwable) {
		$message = $error->getMessage();
	} else {
		$message = $error;
		$error = new \Exception($error);
	}
	return $message;
}

namespace seekat\API\Links;

use React\Promise\{
	ExtendedPromiseInterface,
	function reject
};
use seekat\API;
use seekat\API\Call\Cache;

/**
 * Perform a GET request against the link's "first" relation
 *
 * @return ExtendedPromiseInterface
 */
function first(API $api, Cache\Service $cache = null) : ExtendedPromiseInterface {
	$links = $api->getLinks();
	if ($links && ($first = $links->getFirst())) {
		return $api->withUrl($first)->get(null, null, $cache);
	}
	return reject($links);
}

/**
 * Perform a GET request against the link's "prev" relation
 *
 * @return ExtendedPromiseInterface
 */
function prev(API $api, Cache\Service $cache = null) : ExtendedPromiseInterface {
	$links = $api->getLinks();
	if ($links && ($prev = $links->getPrev())) {
		return $api->withUrl($prev)->get(null, null, $cache);
	}
	return reject($links);
}

/**
 * Perform a GET request against the link's "next" relation
 *
 * @return ExtendedPromiseInterface
 */
function next(API $api, Cache\Service $cache = null) : ExtendedPromiseInterface {
	$links = $api->getLinks();
	if ($links && ($next = $links->getNext())) {
		return $api->withUrl($next)->get(null, null, $cache);
	}
	return reject($links);
}

/**
 * Perform a GET request against the link's "last" relation
 *
 * @return ExtendedPromiseInterface
 */
function last(API $api, Cache\Service $cache = null) : ExtendedPromiseInterface {
	$links = $api->getLinks();
	if ($links && ($last = $links->getLast())) {
		return $api->withUrl($last)->get(null, null, $cache);
	}
	return reject($links);
}

