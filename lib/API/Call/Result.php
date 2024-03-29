<?php

namespace seekat\API\Call;

use http\Client\Response;
use http\Header;
use seekat\API;
use seekat\Exception\RequestException;

final class Result {
	private $api;

	function __construct(API $api) {
		$this->api = $api;
	}

	/**
	 * @param Response $response
	 * @return API
	 * @throws RequestException
	 */
	function __invoke(Response $response) : API {
		$links = $this->checkResponseMeta($response);
		$type = $this->checkResponseType($response);
		$data = $this->checkResponseBody($response, $type);

		$this->api->getLogger()->info("response -> info", [
			"type" => $type->getType(),
			"links" => $links->getRelations(),
		]);
		$this->api->getLogger()->debug("response -> data", [
			"data" => $data,
		]);

		return $this->api = $this->api->with(compact("type", "data", "links"));
	}

	/**
	 * @throws RequestException
	 */
	private function checkResponseMeta(Response $response) : API\Links {
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

	/**
	 * @throws RequestException
	 */
	private function checkResponseType(Response $response) : API\ContentType {
		if (!($type = $response->getHeader("Content-Type", Header::class))) {
			$e = new RequestException($response);

			$this->api->getLogger()->error("response -> error: Empty Content-Type -> ".$e->getMessage(), [
				"url" => (string) $this->api->getUrl(),
			]);

			throw $e;
		}

		return new API\ContentType($this->api->getVersion(), $type->value);
	}

	/**
	 * @throws \Exception
	 */
	private function checkResponseBody(Response $response, API\ContentType $type) : mixed {
		try {
			$data = $type->decode($response->getBody());
		} catch (\Exception $e) {
			$this->api->getLogger()->error("response -> error: ".$e->getMessage(), [
				"url" => (string) $this->api->getUrl(),
			]);

			throw $e;
		}

		return $data;
	}
}
