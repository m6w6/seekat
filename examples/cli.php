#!/usr/bin/env php
<?php

$api = include "examples.inc";

array_shift($argv);

($self = function($api) use(&$self) {
	global $argv;

	while (null !== ($arg = array_shift($argv))) {
		if ("." === $arg) {
			$api->then($self);
			return;
		}
		$api = $api->$arg;
	}

	echo $api, "\n";
})($api);

$api->send();
