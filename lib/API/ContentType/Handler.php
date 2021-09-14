<?php

namespace seekat\API\ContentType;

use http\Message\Body;

interface Handler {
	/**
	 * List handled types
	 * @return array
	 */
	function types() : array;

	/**
	 * Decode HTTP message body
	 * @param Body $body
	 * @return mixed
	 */
	function decode(Body $body);

	/**
	 * Encode HTTP message body
	 * @param $data
	 * @return Body
	 */
	function encode($data) : Body;
}
