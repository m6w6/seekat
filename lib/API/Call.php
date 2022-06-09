<?php

namespace seekat\API;

use http\Url;
use seekat\API;

final class Call {
	function __construct(private readonly API $api, private readonly string $call) {
	}

	function __invoke(array $args) {
		if ($this->api->exists($this->call."_url", $url)) {
			$url = new Url(uri_template($url, (array) current($args)));
			$promise = $this->api->withUrl($url)->get(...$args);
		} else {
			$promise = $this->api->{$this->call}->get(...$args);
		}

		return $promise;
	}
}
