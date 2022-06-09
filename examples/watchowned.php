#!/usr/bin/env php
<?php

use seekat\API\Links;

$api = include "examples.inc";

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
