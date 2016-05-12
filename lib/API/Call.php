<?php

namespace seekat\API;

use Exception;
use http\Client;
use http\Client\Request;
use http\Client\Response;
use React\Promise\Deferred;
use seekat\API;
use SplObserver;
use SplSubject;

class Call extends Deferred implements SplObserver
{
	/**
	 * The endpoint
	 * @var \seekat\API
	 */
	private $api;

	/**
	 * The HTTP client
	 * @var Client
	 */
	private $client;

	/**
	 * The executed request
	 * @var Request
	 */
	private $request;

	/**
	 * The promised response
	 * @var Response
	 */
	private $response;

	/**
	 * Create a deferred promise for the response of $request
	 *
	 * @var \seekat\API $api The endpoint of the request
	 * @var Client $client The HTTP client to send the request
	 * @var Request The request to execute
	 */
	function __construct(API $api, Client $client, Request $request) {
		$this->api = $api;
		$this->client = $client;
		$this->request = $request;

		parent::__construct(function($resolve, $reject) {
			return $this->cancel($resolve, $reject);
		});
		
		$client->attach($this);
		$client->enqueue($request);
		/* start off */
		$client->once();
	}

	/**
	 * Progress observer
	 *
	 * Import the response's data on success and resolve the promise.
	 *
	 * @var SplSubject $client The observed HTTP client
	 * @var Request The request which generated the update
	 * @var object $progress The progress information
	 */
	function update(SplSubject $client, Request $request = null, $progress = null) {
		if ($request !== $this->request) {
			return;
		}

		$this->notify((object) compact("client", "request", "progress"));

		if ($progress->info === "finished") {
			$this->response = $this->client->getResponse();
			$this->complete(
				[$this, "resolve"],
				[$this, "reject"]
			);
		}
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
				$resolve($this->api->import($this->response));
			} catch (Exception $e) {
				$reject($e);
			}
		} else {
			$reject($this->client->getTransferInfo($this->request)["error"]);
		}

		$this->client->dequeue($this->request);
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
