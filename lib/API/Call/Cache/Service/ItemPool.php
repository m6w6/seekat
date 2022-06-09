<?php

namespace seekat\API\Call\Cache\Service;

use http\Client\Response;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use seekat\API\Call\Cache\Service;

final class ItemPool implements Service {
	private ?CacheItemInterface $item;

	public function __construct(private readonly CacheItemPoolInterface $cache) {
	}

	/**
	 * @param string $key
	 * @param Response|null $response
	 * @return bool
	 * @throws \Psr\Cache\InvalidArgumentException
	 */
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

	/**
	 * @param string $key
	 * @throws \Psr\Cache\InvalidArgumentException
	 */
	public function del(string $key) : void {
		$this->cache->deleteItem($key);
	}

	public function clear() : void {
		$this->cache->clear();
	}
}
