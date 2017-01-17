<?php

namespace seekat\API\Call;

use http\Client\Response;
use http\Header;
use seekat\API;
use seekat\Exception\RequestException;

final class Result
{
	private $api;

	function __construct(API $api) {
		$this->api = $api;
	}

	function __invoke(Response $response) : API {
		$hit = $response->getHeader("X-Cache-Time") ? "cached" : "enqueued";
		$this->api->getLogger()->info("$hit -> response", [
			"url"  => (string) $this->api->getUrl(),
			"info" => $response->getInfo(),
		]);

		$links = $this->checkResponseMeta($response);
		$type = $this->checkResponseType($response);

		try {
			$data = $type->parseBody($response->getBody());
		} catch (\Exception $e) {
			$this->api->getLogger()->error("response -> error: ".$e->getMessage(), [
				"url" => (string) $this->api->getUrl(),
			]);

			throw $e;
		}

		return $this->api = $this->api->with(compact("type", "data", "links"));
	}

	/**
	 * @param Response $response
	 * @return null|API\Links
	 * @throws RequestException
	 */
	private function checkResponseMeta(Response $response) {
		if ($response->getResponseCode() >= 400) {
			$e = new RequestException($response);

			$this->api->getLogger()->critical("response -> error: ".$e->getMessage(), [
				"url" => (string) $this->api->getUrl(),
			]);

			throw $e;
		}

		if (!($link = $response->getHeader("Link", Header::class))) {
			$link = null;
		}

		return new API\Links($link);
	}

	private function checkResponseType(Response $response) {
		if (!($type = $response->getHeader("Content-Type", Header::class))) {
			$e = new RequestException($response);

			$this->api->getLogger()->error("response -> error: Empty Content-Type -> ".$e->getMessage(), [
				"url" => (string) $this->api->getUrl(),
			]);

			throw $e;
		}

		return new API\ContentType($type);
	}
}
