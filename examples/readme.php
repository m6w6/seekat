#!/usr/bin/env php
<?php

$api = include "examples.inc";
$api(function($api) {
	echo yield $api->repos->m6w6->seekat->readme->as("raw")->get();
});
