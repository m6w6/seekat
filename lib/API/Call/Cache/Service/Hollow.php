<?php

namespace seekat\API\Call\Cache\Service;

use http\Client\Response;
use seekat\API\Call\Cache\Service;

final class Hollow implements Service {
	private $storage = [];

	public function fetch(string $key, Response &$response = null) : bool {
		if (isset($this->storage[$key])) {
			$response = $this->storage[$key];
			return true;
		}
		return false;
	}

	public function store(string $key, Response $response) : bool {
		$this->storage[$key] = $response;
		return true;
	}

	public function del(string $key) {
		unset($this->storage[$key]);
	}

	public function clear() {
		$this->storage = [];
	}

	public function getStorage() : array {
		return $this->storage;
	}
}
