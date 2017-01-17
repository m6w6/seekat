#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;
use seekat\API\Future;

$log = new Monolog\Logger("seekat");
$log->pushHandler((new Monolog\Handler\StreamHandler(STDERR))->setLevel(Monolog\Logger::NOTICE));

$api = new API(Future\amp(), API\auth("token", getenv("GITHUB_TOKEN")), null, null, $log);

$api->users->m6w6->gists()->when(function($error, $gists) {
	foreach ($gists as $gist) {
		$gist->commits()->when(function($error, $commits) use($gist) {
			foreach ($commits as $i => $commit) {
				if (!$i) {
					printf("\nGist %s, %s:\n", $gist->id, $gist->description ?: "<no title>");
				}
				$cs = $commit->change_status;
				printf("\t%s: ", substr($commit->version,0,8));
				printf("-%s+%s=%s\n", $cs->deletions, $cs->additions, $cs->total);
			}
		});
	}
});

$api->send();
