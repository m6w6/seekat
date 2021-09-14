<?php

namespace seekat\Exception;

use http\{Client\Response, Header};
use seekat\Exception;

/**
 * @code-coverage-ignore
 */
class RequestException extends \Exception implements Exception {
	/**
	 * JSON errors
	 * @var array
	 */
	private $errors = [];

	/**
	 * The response of the request which caused the exception
	 * @var Response
	 */
	private $response;

	/**
	 * @param Response $response
	 */
	function __construct(Response $response) {
		$this->response = $response;

		if (($h = $response->getHeader("Content-Type", Header::class))
		&&	$h->match("application/json", Header::MATCH_WORD)
		&&	$failure = json_decode($response->getBody())) {
			$message = $failure->message;
			if (isset($failure->errors)) {
				$this->errors = (array) $failure->errors;
			}
		} else {
			$message = trim($response->getBody()->toString());
		}

		if (!strlen($message)) {
			$message = $response->getTransferInfo("error");
		}
		if (!strlen($message)) {
			$message = $response->getResponseStatus();
		}

		parent::__construct($message, $response->getResponseCode(), null);
	}

	/**
	 * Get JSON errors
	 * @return array
	 */
	function getErrors() : array {
		return $this->errors;
	}

	/**
	 * @return Response
	 */
	function getResponse() : Response {
		return $this->response;
	}

	/**
	 * Combine any errors into a single string
	 * @staticvar array $reasons
	 * @return string
	 */
	function getErrorsAsString() {
		static $reasons = [
			"missing" => "The resource %1\$s does not exist\n",
			"missing_field" => "Missing field %2\$s of resource %1\$s\n",
			"invalid" => "Invalid formatting of field %2\$s of resource %1\$s\n",
			"already_exists" => "A resource %1\$s with the same value of field %2\$s already exists\n",
		];

		if (!$this->errors) {
			return $this->response;
		}

		$errors = "JSON errors:\n";
		foreach ($this->errors as $error) {
			if ($error->code === "custom") {
				$errors .= $error->message . "\n";
			} else {
				$errors .= sprintf($reasons[$error->code], $error->resource, $error->field);
			}
		}
		return $errors;
	}

	/**
	 * @return string
	 */
	function __toString() : string {
		return parent::__toString() . "\n". $this->getErrorsAsString();
	}
}
