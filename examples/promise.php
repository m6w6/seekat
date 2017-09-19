#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;

$api = new API(API\Future\amp(), API\auth("token", getenv("GITHUB_TOKEN")));

$api->users->m6w6->gists()->onResolve(function($error, $gists) {
	$error and die($error);
	foreach ($gists as $gist) {
		$gist->commits()->onResolve(function($error, $commits) use($gist) {
			$error and die($error);
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
