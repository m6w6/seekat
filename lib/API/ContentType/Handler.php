<?php

namespace seekat\API\ContentType;

use http\Message\Body;

interface Handler {
	/**
	 * List handled types
	 * @return string[]
	 */
	function types() : array;

	/**
	 * Decode HTTP message body
	 */
	function decode(Body $body) : mixed;

	/**
	 * Encode HTTP message body
	 */
	function encode(mixed $data) : Body;
}
