<?php

namespace seekat\API\Call;

use http\Client\Request;
use http\Client\Response;
use Psr\Log\LoggerInterface;
use seekat\API\Call\Cache\Control;
use seekat\API\Call\Cache\Service;
use seekat\API\Call\Cache\Service\Hollow;


final class Cache {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly Service $cache = new Hollow
	) {
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
			$this->logger->warning("cache -> save = invalid key", compact("ctl"));
			return false;
		}

		$time = time();
		if ($time - 1 <= $response->getHeader("X-Cache-Time")) {
			$this->logger->info("cache -> save = no", compact("time"));
			return true;
		}
		$response->setHeader("X-Cache-Time", $time);

		$this->logger->info("cache -> save = yes", compact("time"));
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
			$this->logger->warning("cache -> load = invalid key", compact("ctl"));
			return false;
		}

		if (!$this->cache->fetch($ctl->getKey(), $response)) {
			$this->logger->info("cache -> load = no");
			return false;
		}
		if ($ctl->isStale($response)) {
			if (($lmod = $response->getHeader("Last-Modified"))) {
				$request->setOptions(["lastmodified" => strtotime($lmod)]);
			}
			if (($etag = $response->getHeader("ETag"))) {
				$request->setOptions(["etag" => $etag]);
			}
			$this->logger->info("cache -> load = stale");
			return false;
		}
		$response->setHeader("X-Cache-Hit", $response->getHeader("X-Cache-Hit") + 1);
		$this->logger->info("cache -> load = yes");
		return true;
	}

	/**
	 * Update call data
	 * @param Request $request
	 * @param Response $response
	 * @return bool
	 */
	public function update(Request $request, Response &$response) : bool {
		$ctl = new Control($request);
		if (!$ctl->isValid()) {
			$this->logger->warning("cache -> update = invalid key", compact("ctl"));
			return false;
		}

		if ($response->getResponseCode() !== 304) {
			$this->logger->info("cache -> update = yes");
			return $this->save($request, $response);
		}

		/** @var Response $cached */
		if (!$this->cache->fetch($ctl->getKey(), $cached)) {
			$this->logger->info("cache -> update = yes; no-exist");
			return $this->save($request, $response);
		}

		if ($response->getHeader("ETag") !== $cached->getHeader("ETag")) {
			$this->logger->info("cache -> update = yes; etag");
			return $this->save($request, $response);
		}

		$response = $cached;
		$response->setHeader("X-Cache-Update", $cached->getHeader("X-Cache-Update") + 1);

		$this->logger->info("cache -> update = yes; touch");
		return $this->save($request, $response);
	}
}
