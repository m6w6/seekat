<?php

namespace seekat\API\Links;

use AsyncInterop\Promise;
use seekat\API;
use seekat\API\Call\Cache;
use seekat\API\Future;

/**
 * Perform a GET request against the link's "first" relation
 *
 * @return Promise
 */
function first(API $api, Cache\Service $cache = null) : Promise {
	$links = $api->getLinks();
	if ($links && ($first = $links->getFirst())) {
		return $api->withUrl($first)->get(null, null, $cache);
	}
	return Future\reject($api->getFuture(), $links);
}

/**
 * Perform a GET request against the link's "prev" relation
 *
 * @return Promise
 */
function prev(API $api, Cache\Service $cache = null) : Promise {
	$links = $api->getLinks();
	if ($links && ($prev = $links->getPrev())) {
		return $api->withUrl($prev)->get(null, null, $cache);
	}
	return Future\reject($api->getFuture(), $links);
}

/**
 * Perform a GET request against the link's "next" relation
 *
 * @return Promise
 */
function next(API $api, Cache\Service $cache = null) : Promise {
	$links = $api->getLinks();
	if ($links && ($next = $links->getNext())) {
		return $api->withUrl($next)->get(null, null, $cache);
	}
	return Future\reject($api->getFuture(), $links);
}

/**
 * Perform a GET request against the link's "last" relation
 *
 * @return Promise
 */
function last(API $api, Cache\Service $cache = null) : Promise {
	$links = $api->getLinks();
	if ($links && ($last = $links->getLast())) {
		return $api->withUrl($last)->get(null, null, $cache);
	}
	return Future\reject($api->getFuture(), $links);
}

