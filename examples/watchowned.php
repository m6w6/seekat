#!/usr/bin/env php
<?php

use http\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use seekat\API;
use seekat\API\Links;

require_once __DIR__."/../vendor/autoload.php";

$log = new Monolog\Logger("seekat");
$log->pushHandler((new Monolog\Handler\StreamHandler(STDERR))
	->setLevel(Monolog\Logger::WARNING));

$cli = new http\Client("curl", "seekat");

$api = new API(API\Future\amp(), [
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
], null, $cli, $log);

$api(function($api) {
	$count = 0;
	$subscribed = yield $api->user->subscriptions(["per_page" => 3]);

	while ($subscribed) {
		/* pro-actively queue the next request */
		$next = Links\next($subscribed);
		foreach ($subscribed->getData() as $subscription) {
			if ($subscription->fork) {
				printf("skipping fork %s\n", $subscription->full_name);
				continue;
			}
			++$count;
			printf("watching %s owned by %s (repo is %s)\n",
				$subscription->full_name,
				$subscription->owner->login,
				$subscription->private ? "private" : "public"
			);
		}
		$subscribed = yield $next;
	}
	return $count;
})->onResolve(function($error, $count) {
	printf("Listed %d repos\n", $count);
});
