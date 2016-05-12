#!/usr/bin/env php
<?php

require_once __DIR__."/../vendor/autoload.php";

(new seekat\API)(function($api) {
	echo yield $api->repos->m6w6->seekat->readme->as("raw")->get();
});
