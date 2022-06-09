#!/usr/bin/env php
<?php
$api = include "examples.inc";

$api(function($api) {
	$gists = yield $api->users->m6w6->gists();
	while ($gists) {
		$next = \seekat\API\Links\next($gists);

		foreach ($gists as $gist) {
			foreach ($gist->files as $name => $file) {
				if ($name == "blog.md") {
					$text = $file->raw();
					$head = $gist->description." ".$gist->created_at;
					echo "$head\n";
					echo str_repeat("=", strlen($head))."\n\n";
					echo trim(yield $text);
					echo "\n\nThis was my last gistlog, pipe it through \$PAGER if ";
					echo "you really want to read it or visit https://gistlog.co/m6w6\n";
					return;
				}
			}
		}

		$gists = yield $next;
	}
});
