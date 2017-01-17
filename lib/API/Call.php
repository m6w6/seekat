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
		$promise = $this->api->{$this->call}->get(...$args);

		/* fetch resource, unless already localized, and try for {$method}_url */
		if (!$this->api->exists($this->call)) {
			$promise->when(function($error, $value) use($args) {
				if (!isset($error)) {
					return $value;
				}
				if ($this->api->exists($this->call."_url", $url)) {
					$url = new Url(uri_template($url, (array)current($args)));
					return $this->api->withUrl($url)->get(...$args);
				}

				$message = Exception\message($error);
				$this->api->getLogger()->error("call($this->call): " . $message, [
					"url" => (string) $this->api->getUrl()
				]);

				throw $error;
			});
		}

		return $promise;
	}
}
