<?php

namespace seekat\API;

use seekat\{
	API, Exception\InvalidArgumentException, Exception\UnexpectedValueException
};
use http\{
	Header, Message\Body
};

class ContentType
{
	/**
	 * API version
	 * @var int
	 */
	static private $version = 3;

	/**
	 * Content type handler map
	 * @var array
	 */
	static private $types = [
		"json"		=> "self::fromJson",
		"base64"	=> "self::fromBase64",
		"sha"		=> "self::fromData",
		"raw"		=> "self::fromData",
		"html"		=> "self::fromData",
		"diff"		=> "self::fromData",
		"patch"		=> "self::fromData",
		"text/plain"=> "self::fromData",
		"application/octet-stream" => "self::fromStream",
	];

	/**
	 * Content type abbreviation
	 * @var string
	 */
	private $type;

	/**
	 * Register a content type handler
	 * @param string $type The content type (abbreviation)
	 * @param callable $handler The handler as function(Body $body):mixed;
	 */
	static function register(string $type, callable $handler) {
		self::$types[$type] = $handler;
	}

	/**
	 * Check whether a handler is registered for a particular content type
	 * @param string $type The (abbreviated) content type
	 * @return bool
	 */
	static function registered(string $type) : bool {
		return isset(self::$types[$type]);
	}

	/**
	 * Unregister a content type handler
	 * @param string $type
	 */
	static function unregister(string $type) {
		unset(self::$types[$type]);
	}

	/**
	 * Get/set the API version to use
	 * @param int $v if not null, update the API version
	 * @return int the previously set version
	 */
	static function version(int $v = null) : int {
		$api = self::$version;
		if (isset($v)) {
			self::$version = $v;
		}
		return $api;
	}

	/**
	 * @param API $api
	 * @param string $type
	 * @return API
	 */
	static function apply(API $api, string $type) : API {
		$part = "[^()<>@,;:\\\"\/\[\]?.=[:space:][:cntrl:]]+";
		if (preg_match("/^$part\/$part\$/", $type)) {
			$that = $api->withHeader("Accept", $type);
		} else {
			switch (substr($type, 0, 1)) {
				case "+":
				case ".":
				case "":
					break;
				default:
					$type = ".$type";
					break;
			}
			$vapi = static::version();
			$that = $api->withHeader("Accept", "application/vnd.github.v$vapi$type");
		}
		return $that;
	}

	/**
	 * @param Body $json
	 * @return mixed
	 * @throws UnexpectedValueException
	 */
	private static function fromJson(Body $json) {
		$decoded = json_decode($json);
		if (!isset($decoded) && json_last_error()) {
			throw new UnexpectedValueException("Could not decode JSON: ".
				json_last_error_msg());
		}
		return $decoded;
	}

	/**
	 * @param Body $base64
	 * @return string
	 * @throws UnexpectedValueException
	 */
	private static function fromBase64(Body $base64) : string {
		if (false === ($decoded = base64_decode($base64))) {
			throw new UnexpectedValueException("Could not decode BASE64");
		}
		return $decoded;
	}


	/**
	 * @param Body $stream
	 * @return resource stream
	 */
	private static function fromStream(Body $stream) {
		return $stream->getResource();
	}

	/**
	 * @param Body $data
	 * @return string
	 */
	private static function fromData(Body $data) : string {
		return (string) $data;
	}

	/**
	 * @param Header $contentType
	 * @throws InvalidArgumentException
	 */
	function __construct(Header $contentType) {
		if (strcasecmp($contentType->name, "Content-Type")) {
			throw new InvalidArgumentException(
				"Expected Content-Type header, got ". $contentType->name);
		}
		$vapi = static::version();
		$this->type = preg_replace("/
			(?:application\/(?:vnd\.github(?:\.v$vapi)?)?)
			(?|
					\.					([^.+]+)
				|	(?:\.[^.+]+)?\+?	(json)
			)/x", "\\1", current(array_keys($contentType->getParams()->params)));
	}

	/**
	 * Get the (abbreviated) content type name
	 * @return string
	 */
	function getType() : string {
		return $this->type;
	}

	/**
	 * Parse a response message's body according to its content type
	 * @param Body $data
	 * @return mixed
	 * @throws UnexpectedValueException
	 */
	function parseBody(Body $data) {
		$type = $this->getType();
		if (static::registered($type)) {
			return call_user_func(self::$types[$type], $data, $type);
		}
		throw new UnexpectedValueException("Unhandled content type '$type'");
	}
}
