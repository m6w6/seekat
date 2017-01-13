<?php

namespace seekat\API\Call;

use http\Client\Response;
use http\Header;
use seekat\API;
use seekat\Exception\RequestException;

class Result
{
	private $api;

	function __construct(API $api) {
		$this->api = $api;
	}

	function __invoke(Response $response) : API {
		$url = $this->api->getUrl();
		$log = $this->api->getLogger();
		$log->info(($response->getHeader("X-Cache-Time") ? "cached" : "enqueued")." -> response", [
			"url" => (string) $url,
			"info" => $response->getInfo(),
		]);

		if ($response->getResponseCode() >= 400) {
			$e = new RequestException($response);

			$log->critical(__FUNCTION__.": ".$e->getMessage(), [
				"url" => (string) $url,
			]);

			throw $e;
		}

		if (!($type = $response->getHeader("Content-Type", Header::class))) {
			$e = new RequestException($response);
			$log->error(
				__FUNCTION__.": Empty Content-Type -> ".$e->getMessage(), [
				"url" => (string) $url,
			]);
			throw $e;
		}

		try {
			$type = new API\ContentType($type);
			$data = $type->parseBody($response->getBody());

			if (($link = $response->getHeader("Link", Header::class))) {
				$links = new API\Links($link);
			} else {
				$links = null;
			}

			$this->api = $this->api->with(compact("type", "data", "links"));
		} catch (\Exception $e) {
			$log->error(__FUNCTION__.": ".$e->getMessage(), [
				"url" => (string) $url
			]);

			throw $e;
		}

		return $this->api;
	}
}
