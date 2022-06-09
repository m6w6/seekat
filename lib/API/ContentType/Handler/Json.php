<?php

namespace seekat\API\ContentType\Handler;

use http\Message\Body;
use seekat\API\ContentType\Handler;
use seekat\Exception\InvalidArgumentException;
use seekat\Exception\UnexpectedValueException;
use function seekat\typeof;

final class Json implements Handler {
	/**
	 * @inheritdoc
	 */
	function types() : array {
		return ["json"];
	}

	/**
	 * @param int $flags json_encode() flags
	 */
	function __construct(private readonly int $flags = 0) {
	}

	/**
	 * @inheritdoc
	 */
	function encode(mixed $data): Body {
		if (is_scalar($data)) {
			$json = $data;
		} else {
			$json = json_encode($data, $this->flags);
		}

		if (false === $json) {
			throw new InvalidArgumentException(
				"JSON encoding failed for argument ".typeof($data).
				" \$data; ".json_last_error_msg());
		}
		return (new Body)->append($json);
	}

	/**
	 * @inheritdoc
	 */
	function decode(Body $body) : mixed {
		$data = json_decode($body);
		if (!isset($data) && json_last_error()) {
			throw new UnexpectedValueException("Could not decode JSON: ".
				json_last_error_msg());
		}
		return $data;
	}
}
