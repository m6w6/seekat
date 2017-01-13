<?php

namespace seekat\API\Call\Cache\Service;

use http\Client\Response;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use seekat\API\Call\Cache\Service;

final class ItemPool implements Service
{
	/**
	 * @var CacheItemPoolInterface
	 */
	private $cache;

	/**
	 * @var CacheItemInterface
	 */
	private $item;

	public function __construct(CacheItemPoolInterface $cache) {
		$this->cache = $cache;
	}

	public function fetch(string $key, Response &$response = null) : bool {
		$this->item = $this->cache->getItem($key);
		if ($this->item->isHit()) {
			$response = $this->item->get();
			return true;
		}
		return false;
	}

	public function store(string $key, Response $response) : bool {
		$this->item->set($response);
		return $this->cache->save($this->item);
	}

	public function clear() {
		$this->cache->clear();
	}
}
