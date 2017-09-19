<?php

namespace seekat\API;

use AsyncInterop\Promise;
use http\Url;
use seekat\API;
use seekat\Exception;

final class Call
{
	/**
	 * @var API
	 */
	private $api;

	/**
	 * @var string
	 */
	private $call;

	function __construct(API $api, string $call) {
		$this->api = $api;
		$this->call = $call;
	}

	function __invoke(array $args) : Promise {
		if ($this->api->exists($this->call."_url", $url)) {
			$url = new Url(uri_template($url, (array)current($args)));
			$promise = $this->api->withUrl($url)->get(...$args);
		} else {
			$promise = $this->api->{$this->call}->get(...$args);
		}

		return $promise;
	}
}
