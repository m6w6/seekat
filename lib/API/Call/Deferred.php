<?php

namespace seekat\API\Call;

use http\{Client, Client\Request, Client\Response};
use Psr\Log\LoggerInterface;
use seekat\API;

final class Deferred {
	private Result $result;
	private Client $client;
	private LoggerInterface $logger;
	private Cache $cache;

	/**
	 * The promised response
	 */
	private ?Response $response = null;

	/**
	 * @var mixed
	 */
	private object $promise;
	private \Closure $resolve;
	private \Closure $reject;

	/**
	 * Create a deferred promise for the response of $request
	 *
	 * @param API $api The endpoint of the request
	 * @param Request $request The request to execute
	 */
	function __construct(API $api, private readonly Request $request) {
		$this->result = new Result($api);
		$this->client = $api->getClient();
		$this->logger = $api->getLogger();
		$this->cache  = new Cache($this->logger, $api->getCache());

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
		$this->resolve = $future->resolver($context);
		$this->reject = $future->rejecter($context);
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

	private function refresh(Response $cached = null) : void {
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
	private function complete(string $by = "enqueued") : void {
		$this->logger->info("complete -> $by");

		if ($this->response) {
			$this->logger->info("$by -> response", [
				"url"  => $this->request->getRequestUrl(),
				"info" => $this->response->getInfo(),
			]);

			try {
				$this->cache->update($this->request, $this->response);
				($this->resolve)(($this->result)($this->response));
			} catch (\Throwable $e) {
				$this->logger->warning("$by -> cache", ["exception" => $e]);
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
