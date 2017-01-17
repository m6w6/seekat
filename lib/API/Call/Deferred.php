<?php

namespace seekat\API\Call;

use AsyncInterop\Promise;
use Exception;
use http\{
	Client, Client\Request, Client\Response
};
use Psr\Log\LoggerInterface;
use seekat\API;
use SplObserver;
use SplSubject;

final class Deferred implements SplObserver
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
	 * @var Promise
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
				$this->client->detach($this);
				$this->client->dequeue($this->request);
				($this->reject)("Cancelled");
			}
		});
		$this->promise = $future->getPromise($context);
		$this->resolve = API\Future\resolver($future, $context);
		$this->reject = API\Future\rejecter($future, $context);
		$this->update = API\Future\updater($future, $context);
	}

	function __invoke() : Promise {
		if ($this->cache->load($this->request, $cached)) {
			$this->logger->info("deferred -> cached", [
				"method" => $this->request->getRequestMethod(),
				"url" => $this->request->getRequestUrl(),
			]);

			$this->response = $cached;
			$this->complete();
		} else {
			$this->client->attach($this);
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
	 * Progress observer
	 *
	 * Import the response's data on success and resolve the promise.
	 *
	 * @param SplSubject $client The observed HTTP client
	 * @param Request $request The request which generated the update
	 * @param object $progress The progress information
	 */
	function update(SplSubject $client, Request $request = null, $progress = null) {
		if ($request !== $this->request) {
			return;
		}

		($this->update)((object) compact("client", "request", "progress"));
	}

	/**
	 * Completion callback
	 * @param callable $resolve
	 * @param callable $reject
	 */
	private function complete() {
		$this->client->detach($this);

		if ($this->response) {
			try {
				$api = ($this->result)($this->response);

				$this->cache->save($this->request, $this->response);

				($this->resolve)($api);
			} catch (Exception $e) {
				($this->reject)($e);
			}
		} else {
			($this->reject)($this->client->getTransferInfo($this->request)->error);
		}
	}

}
