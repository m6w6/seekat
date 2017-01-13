<?php

namespace seekat\API;

use http\Url;
use React\Promise\ExtendedPromiseInterface;
use seekat\API;
use seekat\Exception;

class Call
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

	function __invoke(array $args) : ExtendedPromiseInterface {
		$promise = $this->api->{$this->call}->get(...$args);

		/* fetch resource, unless already localized, and try for {$method}_url */
		if (!$this->api->exists($this->call)) {
			$promise = $promise->otherwise(function ($error) use($args) {
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
