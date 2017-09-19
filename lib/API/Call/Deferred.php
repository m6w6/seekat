<?php

namespace seekat\API\Call;

use http\{
	Client, Client\Request, Client\Response
};
use Psr\Log\LoggerInterface;
use seekat\API;

final class Deferred
{
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
	 * @var \Closure
	 */
	private $update;

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
		if ($this->cache->load($this->request, $cached)) {
			$this->logger->info("deferred -> cached", [
				"method" => $this->request->getRequestMethod(),
				"url" => $this->request->getRequestUrl(),
			]);

			$this->response = $cached;
			$this->complete();
		} else {
			$this->client->enqueue($this->request, function(Response $response) use($cached) {
				if ($response->getResponseCode() == 304) {
					$this->response = $cached;
				} else {
					$this->response = $response;
				}
				$this->complete();
				return true;
			});
			$this->logger->info("deferred -> enqueued", [
				"method" => $this->request->getRequestMethod(),
				"url" => $this->request->getRequestUrl(),
			]);
			/* start off */
			$this->client->once();
		}

		return $this->promise;
	}

	/**
	 * Completion callback
	 */
	private function complete() {
		if ($this->response) {
			try {
				$api = ($this->result)($this->response);

				$this->cache->save($this->request, $this->response);

				($this->resolve)($api);
			} catch (\Throwable $e) {
				($this->reject)($e);
			}
		} else {
			($this->reject)($this->client->getTransferInfo($this->request)->error);
		}
	}

}
