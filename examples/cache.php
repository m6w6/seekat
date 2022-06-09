#!/usr/bin/env php
<?php

require_once __DIR__ . "/../vendor/autoload.php";

$redis = new Redis;
$redis->connect("localhost");
$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
$cache = new class($redis) implements \seekat\API\Call\Cache\Service {
	function __construct(private readonly Redis $redis) {
	}
	function clear() : void {
		$this->redis->flushDB();
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
	function del(string $key) : void {
		$this->redis->del($key);
	}
};

$log_level = "INFO";

$api = include "examples.inc";

$api(function($api) use($cache) {
	yield $api->users->m6w6();
	yield $api->users->m6w6();
	//$cache->clear();
});
