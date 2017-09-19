#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

$api = new seekat\API(seekat\API\Future\react(), [
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
]);
array_shift($argv);

($self = function($error, $api) use(&$self) {
	global $argv;

	while (null !== ($arg = array_shift($argv))) {
		if ("." === $arg) {
			$api->when($self);
			return;
		}
		$api = $api->$arg;
	}

	echo $api, "\n";
})(null, $api);

$api->send();
