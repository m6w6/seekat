<?php

namespace seekat\API\ContentType\Handler;

use http\Message\Body;
use seekat\API\ContentType\Handler;
use seekat\Exception\InvalidArgumentException;
use seekat\Exception\UnexpectedValueException;
use function seekat\typeof;

final class Base64 implements Handler {
	/**
	 * @inheritdoc
	 */
	function types() : array {
		return ["base64"];
	}

	/**
	 * @inheritdoc
	 * @param string $data
	 */
	function encode($data): Body {
		if (!is_scalar($data)) {
			throw new InvalidArgumentException(
				"BASE64 encoding argument must be scalar, got ".typeof($data));
		}
		return (new Body)->append(base64_encode($data));
	}

	/**
	 * @inheritdoc
	 * @return string
	 */
	function decode(Body $body) {
		$data = base64_decode($body, true);

		if (false === $data) {
			$e = error_get_last();
			throw new UnexpectedValueException("Could not decode BASE64: ".
				($e ? $e["message"] : "Unknown error"));
		}

		return $data;
	}
}
