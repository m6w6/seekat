#!/usr/bin/env php
<?php

require __DIR__."/../vendor/autoload.php";

use Monolog\{
	Handler\StreamHandler, Logger
};

$log = new Logger("seekat");
$log->pushHandler(new StreamHandler(STDERR, Logger::INFO));

$redis = new Redis;
$redis->connect("localhost");
$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
$cache = new class($redis) implements \seekat\API\Call\Cache\Service {
	private $redis;
	function __construct(Redis $redis) {
		$this->redis = $redis;
	}
	function clear() {
		return $this->redis->flushDB();
	}
	function fetch(string $key, \http\Client\Response &$response = null): bool {
		list($exists, $response) = $this->redis
			->multi()
			->exists($key)
			->get($key)
			->exec();
		return $exists;
	}
	function store(string $key, \http\Client\Response $response): bool {
		return $this->redis->set($key, $response);
	}
};

$api = new seekat\API(seekat\API\Future\react(), [
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
], null, null, $log, $cache);

$api(function($api) use($cache) {
	yield $api->users->m6w6();
	yield $api->users->m6w6();
	$cache->clear();
});
