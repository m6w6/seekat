<?php

namespace seekat\API\Call\Cache;

use http\Client\Response;

interface Service
{
	function fetch(string $key, Response &$response = null) : bool;
	function store(string $key, Response $response) : bool;
	function clear();
}
