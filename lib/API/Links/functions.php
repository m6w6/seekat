<?php

namespace seekat\API\Links;

use seekat\API;
use seekat\API\Call\Cache;
use seekat\API\Future;

/**
 * Perform a GET request against the link's "first" relation
 *
 * @return mixed promise
 */
function first(API $api, Cache\Service $cache = null) {
	$links = $api->getLinks();
	if ($links && ($first = $links->getFirst())) {
		return $api->withUrl($first)->get(null, null, $cache);
	}
	return Future\resolve($api->getFuture(), null);
}

/**
 * Perform a GET request against the link's "prev" relation
 *
 * @return mixed promise
 */
function prev(API $api, Cache\Service $cache = null) {
	$links = $api->getLinks();
	if ($links && ($prev = $links->getPrev())) {
		return $api->withUrl($prev)->get(null, null, $cache);
	}
	return Future\resolve($api->getFuture(), null);
}

/**
 * Perform a GET request against the link's "next" relation
 *
 * @return mixed promise
 */
function next(API $api, Cache\Service $cache = null) {
	$links = $api->getLinks();
	if ($links && ($next = $links->getNext())) {
		return $api->withUrl($next)->get(null, null, $cache);
	}
	return Future\resolve($api->getFuture(), null);
}

/**
 * Perform a GET request against the link's "last" relation
 *
 * @return mixed promise
 */
function last(API $api, Cache\Service $cache = null) {
	$links = $api->getLinks();
	if ($links && ($last = $links->getLast())) {
		return $api->withUrl($last)->get(null, null, $cache);
	}
	return Future\resolve($api->getFuture(), null);
}

