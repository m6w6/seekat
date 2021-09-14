<?php

namespace seekat\API\ContentType\Handler;

use http\Message\Body;
use seekat\API\ContentType\Handler;

final class Stream implements Handler {
	/**
	 * @inheritdoc
	 */
	function types() : array {
		return ["application/octet-stream"];
	}

	/**
	 * @inheritdoc
	 * @param resource $data
	 */
	function encode($data): Body {
		return new Body($data);
	}

	/**
	 * @inheritdoc
	 * @return resource
	 */
	function decode(Body $body) {
		return $body->getResource();
	}
}
