#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\{API, API\Future, API\Links};
use Monolog\{Logger, Handler};

$cli = new http\Client("curl", "seekat");
$cli->configure([
	"max_host_connections" => 10,
	"max_total_connections" => 50,
	"use_eventloop" => true,
]);

$log = new Logger("seekat");
$log->pushHandler(new Handler\StreamHandler(STDERR, Logger::NOTICE));

$api = new API(Future\react(), API\auth("token", getenv("GITHUB_TOKEN")), null, $cli, $log);

$api(function() use($api) {
	$repos = yield $api->users->m6w6->repos([
		"visibility" => "public",
		"affiliation" => "owner"
	]);
	while ($repos) {
		$next = Links\next($repos);

		$batch = [];
		foreach ($repos as $repo) {
			$batch[] = $repo->hooks();
		}
		$result = yield $batch;
		foreach ($result as $key => $hooks) {
			if (!count($hooks)) {
				continue;
			}
			printf("%s:\n", $repos->{$key}->name);
			foreach ($hooks as $hook) {
				if ($hook->name == "web") {
					printf("\t%s\n", $hook->config->url);
				} else {
					printf("\t%s\n", $hook->name);
				}
			}
		}

		$repos = yield $next;
	}
});
