<?php

namespace seekat\API\Call\Cache\Service;

use http\Client\Response;
use Psr\SimpleCache\CacheInterface;
use seekat\API\Call\Cache\Service;

final class Simple implements Service {
	/**
	 * @var CacheInterface
	 */
	private $cache;

	public function __construct(CacheInterface $cache) {
		$this->cache = $cache;
	}

	public function fetch(string $key, Response &$response = null) : bool {
		$response = $this->cache->get($key);
		return !!$response;
	}

	public function store(string $key, Response $response) : bool {
		return $this->cache->set($key, $response);
	}

	public function del(string $key) {
		$this->cache->delete($key);
	}

	public function clear() {
		$this->cache->clear();
	}
}
