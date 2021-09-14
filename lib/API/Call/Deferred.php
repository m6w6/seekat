<?php

namespace seekat\API\Call;

use http\{Client, Client\Request, Client\Response};
use Psr\Log\LoggerInterface;
use seekat\API;

final class Deferred {
	/**
	 * The response importer
	 *
	 * @var Result
	 */
	private $result;

	/**
	 * The HTTP client
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Request cache
	 *
	 * @var callable
	 */
	private $cache;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The executed request
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * The promised response
	 *
	 * @var Response
	 */
	private $response;

	/**
	 * @var mixed
	 */
	private $promise;

	/**
	 * @var \Closure
	 */
	private $resolve;

	/**
	 * @var \Closure
	 */
	private $reject;

	/**
	 * Create a deferred promise for the response of $request
	 *
	 * @param API $api The endpoint of the request
	 * @param Request $request The request to execute
	 * @param Cache\Service $cache
	 */
	function __construct(API $api, Request $request, Cache\Service $cache = null) {
		$this->request = $request;
		$this->client = $api->getClient();
		$this->logger = $api->getLogger();
		$this->result = new Result($api);
		$this->cache = new Cache($cache);

		$future = $api->getFuture();
		$context = $future->createContext(function() {
			if ($this->response) {
				/* we did finish in the meantime */
				$this->complete();
			} else {
				$this->client->dequeue($this->request);
				($this->reject)("Cancelled");
			}
		});
		$this->promise = $future->getPromise($context);
		$this->resolve = API\Future\resolver($future, $context);
		$this->reject = API\Future\rejecter($future, $context);
	}

	function __invoke() {
		if (!$this->cached($cached)) {
			$this->refresh($cached);
		}

		return $this->promise;
	}

	/**
	 * Peek into cache
	 *
	 * @param Response $cached
	 * @return bool
	 */
	private function cached(Response &$cached = null) : bool {
		$fresh = $this->cache->load($this->request, $cachedResponse);

		if (!$cachedResponse) {
			return false;
		} else {
			$cached = $cachedResponse;

			$this->logger->info("deferred -> cached", [
				"method" => $this->request->getRequestMethod(),
				"url" => $this->request->getRequestUrl(),
			]);


			if (!$fresh) {
				$this->logger->info("cached -> stale", [
					"method" => $this->request->getRequestMethod(),
					"url"    => $this->request->getRequestUrl(),
				]);
				return false;
			}
		}

		$this->response = $cached;
		$this->complete("cached");
		return true;
	}

	/**
	 * Refresh
	 *
	 * @param Response|null $cached
	 */
	private function refresh(Response $cached = null) {
		$this->client->enqueue($this->request, function(Response $response) use($cached) {
			$this->response = $response;
			$this->complete();
			return true;
		});

		$this->logger->info(($cached ? "stale" : "deferred") . " -> enqueued", [
			"method" => $this->request->getRequestMethod(),
			"url" => $this->request->getRequestUrl(),
		]);

		/* start off */
		$this->client->once();
	}

	/**
	 * Completion callback
	 */
	private function complete(string $by = "enqueued") {
		if ($this->response) {
			$this->logger->info("$by -> response", [
				"url"  => $this->request->getRequestUrl(),
				"info" => $this->response->getInfo(),
			]);

			try {
				$this->cache->update($this->request, $this->response);
				($this->resolve)(($this->result)($this->response));
			} catch (\Throwable $e) {
				($this->reject)($e);
			}
		} else {
			$info = $this->client->getTransferInfo($this->request);

			$this->logger->warning("$by -> no response", [
				"url"  => $this->request->getRequestUrl(),
				"info" => $info
			]);

			($this->reject)($info->error);
		}
	}

}
