<?php

namespace seekat\API\ContentType\Handler;

use http\Message\Body;
use seekat\API\ContentType\Handler;
use seekat\Exception\InvalidArgumentException;
use function seekat\typeof;

final class Text implements Handler {
	/**
	 * @inheritdoc
	 */
	function types() : array {
		return ["sha", "raw", "html", "diff", "patch", "text/plain"];
	}

	/**
	 * @inheritdoc
	 */
	function encode(mixed $data): Body {
		if (isset($data) && !is_scalar($data)) {
			throw new InvalidArgumentException(
				"Text encoding argument must be scalar, got ".typeof($data));
		}
		return (new Body)->append((string) $data);
	}

	/**
	 * @inheritdoc
	 * @return string
	 */
	function decode(Body $body) : string {
		return (string) $body;
	}
}
