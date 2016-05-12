<?php

namespace seekat\API;

use http\Header;
use http\Message\Body;

class ContentType
{
	static private $version = 3;
	
	static private $types = [
		"json"		=> "self::fromJson",
		"base64"	=> "self::fromBase64",
		"sha"		=> "self::fromData",
		"raw"		=> "self::fromData",
		"html"		=> "self::fromData",
		"diff"		=> "self::fromData",
		"patch"		=> "self::fromData",
	];

	private $type;

	static function register(string $type, callable $handler) {
		self::$types[$type] = $handler;
	}

	static function registered(string $type) : bool {
		return isset(self::$types[$type]);
	}

	static function unregister(string $type) {
		unset(self::$types[$type]);
	}

	static function version(int $v = null) : int {
		$api = self::$version;
		if (isset($v)) {
			self::$version = $v;
		}
		return $api;
	}
	
	private static function fromJson(Body $json) {
		$decoded = json_decode($json);
		if (!isset($decoded) && json_last_error()) {
			throw new \UnexpectedValueException("Could not decode JSON: ".
				json_last_error_msg());
		}
		return $decoded;
	}

	private static function fromBase64(Body $base64) : string {
		if (false === ($decoded = base64_decode($base64))) {
			throw new \UnexpectedValueExcpeption("Could not decode BASE64");
		}
	}

	private static function fromData(Body $data) : string {
		return (string) $data;
	}

	function __construct(Header $contentType) {
		if (strcasecmp($contentType->name, "Content-Type")) {
			throw new \InvalidArgumentException(
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

	function getType() : string {
		return $this->type;
	}

	function parseBody(Body $data) {
		$type = $this->getType();
		if (static::registered($type)) {
			return call_user_func(self::$types[$type], $data, $type);
		}
		throw new \UnexpectedValueException("Unhandled content type '$type'");
	}
}
