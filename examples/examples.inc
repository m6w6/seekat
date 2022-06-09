<?php

use seekat\API;

require_once __DIR__."/../vendor/autoload.php";

$log_level = Monolog\Level::fromName(getenv("LOG_LEVEL") ?: "WARNING");

return new API(
	future: $future ?? API\Future\any(),
	headers: $headers ?? API\auth("token", getenv("GITHUB_TOKEN")),
	client: $client ?? new http\Client("curl", "seekat"),
	logger: $logger ?? (new Monolog\Logger("seekat"))
		->pushHandler(new Monolog\Handler\StreamHandler(STDERR, $log_level)),
	cache: $cache ?? new API\Call\Cache\Service\Hollow
);
