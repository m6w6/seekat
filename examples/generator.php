#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;

$log = new Monolog\Logger("seekat");
$log->pushHandler((new Monolog\Handler\StreamHandler(STDERR))->setLevel(Monolog\Logger::INFO));

$cli = new http\Client("curl", "seekat");

$api = new API([
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
], null, $cli, $log);

$api(function() use($api) {
	$count = 0;
	$events = yield $api->repos->m6w6->{"ext-http"}->issues->events();
	while ($events) {
		/* pro-actively queue the next request */
		$next = $events->next();

		foreach ($events as $event) {
			if ($event->event == "labeled") {
				continue;
			}
			++$count;
			printf("@%s %s issue #%d (%s) at %s\n",
				$event->actor->login,
				$event->event,
				(int) (string) $event->issue->number,
				$event->issue->title,
				$event->created_at
			);
		}
		$events = yield $next;
	}
	return $count;
})->done(function($count) {
	printf("Listed %d events\n", $count);
});

