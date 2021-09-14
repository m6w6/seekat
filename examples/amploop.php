#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;
use Amp\Loop as AmpLoop;

$log = new Monolog\Logger("seekat");
$log->pushHandler((new Monolog\Handler\StreamHandler(STDERR))->setLevel(Monolog\Logger::INFO));

$cli = new http\Client("curl", "seekat");
$cli->configure([
	"use_eventloop" => new class($cli, AmpLoop::get()) implements http\Client\Curl\User {

	private $driver;
	private $timeout;
	private $client;
	private $runcb;

	function __construct(http\Client $client, AmpLoop\Driver $driver) {
		$this->client = $client;
		$this->driver = $driver;
	}
	function init($run) {
		$this->runcb = $run;
	}
	function run($socket = null, int $action = null) {
		if ($socket) {
			$remaining = ($this->runcb)($this->client, $socket, $action);
		} else {
			$remaining = ($this->runcb)($this->client);
		}

		if (!$remaining) {
			$this->done();
		}
	}
	function once() {
		if (!$this->driver->getInfo()["running"]) {
			$this->driver->run();
		}
	}
	function send() {
		# unused
		throw new BadMethodCallException(__FUNCTION__);
	}
	function wait(int $timeout_ms = null) {
		# unused
		throw new BadMethodCallException(__FUNCTION__);
	}
	function timer(int $timeout_ms = null) {
		if ($this->timeout) {
			$this->driver->cancel($this->timeout);
		}

		$this->timeout = $this->driver->delay($timeout_ms, function() {
			$this->run();
		});
	}
	function done() {
		if ($this->timeout) {
			$this->driver->cancel($this->timeout);
			$this->timeout = null;
		}
	}
	function socket($socket, int $poll) {
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
}]);

$api = new API(API\Future\amp(), [
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
], null, $cli, $log);

AmpLoop::run(function() use($api) {
	list($m6w6, $seekat) = yield [$api->users->m6w6(), $api->repos->m6w6->seekat()];
	printf("Hi, my name is %s!\n", $m6w6->login);
	printf("Have fun with %s; %s!\n", $seekat->name, $seekat->description);
});
