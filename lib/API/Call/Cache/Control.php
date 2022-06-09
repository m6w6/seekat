<?php

namespace seekat\API\Call\Cache;

use http\Client\Request;
use http\Client\Response;
use http\Header;
use http\Params;
use http\QueryString;
use http\Url;

final class Control {
	private string $key;

	/**
	 * @param Request $request
	 */
	function __construct(Request $request) {
		$method = $request->getRequestMethod();
		switch ($method) {
			case "HEAD":
			case "GET":
				$uid = $this->extractAuth($request);
				$url = $request->getRequestUrl();
				$this->key = "seekat call $uid $method $url";
				break;
			default:
				$this->key = "";
				break;
		}
	}

	/**
	 * @return bool
	 */
	public function isValid() : bool {
		return strlen($this->key) > 0;
	}

	/**
	 * @return string
	 */
	public function getKey() : string {
		return $this->key;
	}

	/**
	 * @param Response $response
	 * @return bool
	 */
	public function isStale(Response $response) : bool {
		if (false === $response->getHeader("Cache-Control") &&
			false === $response->getHeader("Expires")) {
			return false;
		}

		$max_age = $this->extractMaxAge($response);
		$cur_age = time() - $response->getHeader("X-Cache-Time");

		return $max_age >= 0 && $max_age < $cur_age;
	}

	/**
	 * @param Response $response
	 * @return int
	 */
	private function extractMaxAge(Response $response) : int {
		/* @var Header $date
		 * @var Header $control
		 * @var Header $expires
		 */
		$control = $response->getHeader("Cache-Control", Header::class);
		if ($control) {
			/* @var Params $params */
			$params = $control->getParams();
			if (isset($params["max-age"])) {
				return (int) $params->params["max-age"]["value"];
			}
		}

		$expires = $response->getHeader("Expires", Header::class);
		if ($expires) {
			if ($expires->match(0, Header::MATCH_FULL)) {
				return 0;
			}

			$date = $response->getHeader("Date", Header::class);
			if ($date) {
				return strtotime($expires->value) - strtotime($date->value);
			}
		}

		return -1;
	}

	/**
	 * @param Request $request
	 * @return string
	 */
	private function extractAuth(Request $request) : string {
		$auth = $request->getHeader("Authorization");
		if ($auth) {
			return substr($auth, strpos($auth, " ") + 1);
		}

		$opts = $request->getOptions();
		if (isset($opts["httpauth"])) {
			return base64_encode($opts["httpauth"]);
		}

		$query = new QueryString((new Url($request->getRequestUrl()))->query);
		return $query->getString("access_token", $query->getString("client_id", ""));
	}
}
