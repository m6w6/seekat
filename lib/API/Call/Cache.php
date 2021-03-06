<?php

namespace seekat\API\Call;

use http\Client\Request;
use http\Client\Response;
use seekat\API\Call\Cache\Control;
use seekat\API\Call\Cache\Service;
use seekat\API\Call\Cache\Service\Hollow;


class Cache
{
	/**
	 * @var Service
	 */
	private $cache;

	/**
	 * @param Service $cache
	 */
	public function __construct(Service $cache = null) {
		$this->cache = $cache ?? new Hollow;
	}

	/**
	 * Save call data
	 * @param Request $request
	 * @param Response $response
	 * @return bool
	 */
	public function save(Request $request, Response $response) : bool {
		$ctl = new Control($request);
		if (!$ctl->isValid()) {
			return false;
		}

		$time = time();
		if ($time - 1 <= $response->getHeader("X-Cache-Time")) {
			return true;
		}
		$response->setHeader("X-Cache-Time", $time);

		return $this->cache->store($ctl->getKey(), $response);
	}

	/**
	 * Attempt to load call data
	 * @param Request $request
	 * @param Response $response out param
	 * @return bool
	 */
	public function load(Request $request, Response &$response = null) : bool {
		$ctl = new Control($request);
		if (!$ctl->isValid()) {
			return false;
		}

		if (!$this->cache->fetch($ctl->getKey(), $response)) {
			return false;
		}
		if ($ctl->isStale($response)) {
			if (($lmod = $response->getHeader("Last-Modified"))) {
				$request->setOptions(["lastmodified" => strtotime($lmod)]);
			}
			if (($etag = $response->getHeader("ETag"))) {
				$request->setOptions(["etag" => $etag]);
			}
			return false;
		}
		return true;
	}

}
