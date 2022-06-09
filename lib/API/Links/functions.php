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
function first(API $api) {
	if (($first = $api->getLinks()?->getFirst())) {
		return $api->withUrl($first)->get();
	}
	return $api->getFuture()->resolve(null);
}

/**
 * Perform a GET request against the link's "prev" relation
 *
 * @return mixed promise
 */
function prev(API $api) {
	if (($prev = $api->getLinks()?->getPrev())) {
		return $api->withUrl($prev)->get();
	}
	return $api->getFuture()->resolve(null);
}

/**
 * Perform a GET request against the link's "next" relation
 *
 * @return mixed promise
 */
function next(API $api) {
	if (($next = $api->getLinks()?->getNext())) {
		return $api->withUrl($next)->get();
	}
	return $api->getFuture()->resolve(null);
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
	return $api->getFuture()->resolve(null);
}

