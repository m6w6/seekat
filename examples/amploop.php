#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;
use Amp\Loop as AmpLoop;

$client = new http\Client("curl", "seekat");
$client->configure([
	"use_eventloop" => new class($client, AmpLoop::get()) implements http\Client\Curl\User {

		/**
		 * Timeout ID
		 */
		private ?string $timeout = null;
		private \Closure $runcb;

		function __construct(private http\Client $client, private AmpLoop\Driver $driver) {
		}

		function init($run) : void {
			$this->runcb = $run(...);
		}

		/**
		 * @param resource $socket
		 */
		function run($socket = null, int $action = null) : void {
			if ($socket) {
				$remaining = ($this->runcb)($this->client, $socket, $action);
			} else {
				$remaining = ($this->runcb)($this->client);
			}

			if (!$remaining) {
				$this->done();
			}
		}
		function once() : void {
			if (!$this->driver->getInfo()["running"]) {
				$this->driver->run();
			}
		}
		function send() : never {
			# unused
			throw new BadMethodCallException(__FUNCTION__);
		}
		function wait(int $timeout_ms = null) : never {
			# unused
			throw new BadMethodCallException(__FUNCTION__);
		}
		function timer(int $timeout_ms = null) : void {
			if ($this->timeout) {
				$this->driver->cancel($this->timeout);
			}

			$this->timeout = $this->driver->delay($timeout_ms, function() {
				$this->run();
			});
		}
		function done() : void {
			if ($this->timeout) {
				$this->driver->cancel($this->timeout);
				$this->timeout = null;
			}
		}

		/**
		 * @param resource $socket
		 */
		function socket($socket, int $poll) : void {
			foreach ((array) $this->driver->getState((string) $socket) as $id) {
				$this->driver->disable($id);
			}
			switch ($poll) {
			case self::POLL_NONE:
				break;
			case self::POLL_IN:
				$id = $this->driver->onReadable($socket, function($id, $socket) {
					$this->run($socket, self::POLL_IN);
				});
				$this->driver->setState((string) $socket, $id);
				break;
			case self::POLL_OUT:
				$id = $this->driver->onWritable($socket, function($id, $socket) {
					$this->run($socket, self::POLL_OUT);
				});
				$this->driver->setState((string) $socket, $id);
				break;
			case self::POLL_INOUT:
				$id = [
					$this->driver->onReadable($socket, function($id, $socket) {
						$this->run($socket, self::POLL_IN);
					}),
					$this->driver->onWritable($socket, function($id, $socket) {
						$this->run($socket, self::POLL_OUT);
					})
				];
				$this->driver->setState((string) $socket, $id);
				break;
			case self::POLL_REMOVE:
				foreach ((array) $this->driver->getState((string) $socket) as $id) {
					$this->driver->cancel($id);
				}
				$this->driver->setState((string) $socket, null);
				break;
			}
		}
	}
]);

$future = API\Future\amp();

$api = include "examples.inc";

AmpLoop::run(function() use($api) {
	list($m6w6, $seekat) = yield [$api->users->m6w6(), $api->repos->m6w6->seekat()];
	printf("Hi, my name is %s!\n", $m6w6->login);
	printf("Have fun with %s; %s!\n", $seekat->name, $seekat->description);
});
