#!/usr/bin/env php
<?php

use seekat\API\Links;

$api = include "examples.inc";

$api(function($api) {
	$count = 0;
	$events = yield $api->repos->m6w6->{"ext-pq"}->issues->events();
	while ($events) {
		/* pro-actively queue the next request */
		$next = Links\next($events);

		foreach ($events as $event) {
			if ($event->event == "labeled" || $event->event == "unlabeled") {
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
})->onResolve(function($error, $count) {
	printf("Listed %d events\n", $count);
});

