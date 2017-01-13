<?php

namespace seekat\Exception;

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

