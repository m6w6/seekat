#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;

$log = new Monolog\Logger("seekat");
$log->pushHandler((new Monolog\Handler\StreamHandler(STDERR))->setLevel(Monolog\Logger::INFO));

$api = new API([
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
], null, null, $log);

$api->users->m6w6->gists()->done(function($gists) {
	foreach ($gists as $gist) {
		$gist->commits()->then(function($commits) use($gist) {
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