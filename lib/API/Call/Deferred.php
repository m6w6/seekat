<?php

namespace seekat\API\Call;

use Exception;
use http\{
	Client, Client\Request, Client\Response
};
use seekat\API;
use SplObserver;
use SplSubject;

final class Deferred extends \React\Promise\Deferred implements SplObserver
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
	 * Create a deferred promise for the response of $request
	 *
	 * @param API $api The endpoint of the request
	 * @param Request $request The request to execute
	 * @param Cache\Service $cache
	 */
	function __construct(API $api, Request $request, Cache\Service $cache = null) {
		parent::__construct(function ($resolve, $reject) {
			return $this->cancel($resolve, $reject);
		});

		$this->request = $request;
		$this->client = $api->getClient();
		$this->result = new Result($api);
		$this->cache = new Cache($cache);

		if ($this->cache->load($this->request, $cached)) {
			$api->getLogger()->info("deferred -> cached", [
				"method" => $request->getRequestMethod(),
				"url" => $request->getRequestUrl(),
			]);

			$this->response = $cached;
			$this->complete(
				[$this, "resolve"],
				[$this, "reject"]
			);
		} else {
			$this->client->attach($this);
			$this->client->enqueue($this->request, function(Response $response) use($cached) {
				if ($response->getResponseCode() == 304) {
					$this->response = $cached;
				} else {
					$this->response = $response;
				}
				$this->complete(
					[$this, "resolve"],
					[$this, "reject"]
				);
				return true;
			});
			$api->getLogger()->info("deferred -> enqueued", [
				"method" => $request->getRequestMethod(),
				"url" => $request->getRequestUrl(),
			]);
			/* start off */
			$this->client->once();
		}
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

		$this->notify((object) compact("client", "request", "progress"));
	}

	/**
	 * Completion callback
	 * @param callable $resolve
	 * @param callable $reject
	 */
	private function complete(callable $resolve, callable $reject) {
		$this->client->detach($this);

		if ($this->response) {
			try {
				$api = ($this->result)($this->response);

				$this->cache->save($this->request, $this->response);

				$resolve($api);
			} catch (Exception $e) {
				$reject($e);
			}
		} else {
			$reject($this->client->getTransferInfo($this->request)->error);
		}
	}

	/**
	 * Cancellation callback
	 * @param callable $resolve
	 * @param callable $reject
	 */
	private function cancel(callable $resolve, callable $reject) {
		/* did we finish in the meantime? */
		if ($this->response) {
			$this->complete($resolve, $reject);
		} else {
			$this->client->detach($this);
			$this->client->dequeue($this->request);
			$reject("Cancelled");
		}
	}
}
