<?php

namespace seekat\API;

use http\Header;
use http\Message\Body;
use http\Params;
use seekat\API;
use seekat\API\ContentType\Handler;
use seekat\Exception\InvalidArgumentException;
use seekat\Exception\UnexpectedValueException;

final class ContentType {
	/**
	 * @var int
	 */
	private $version;

	/**
	 * Content type abbreviation
	 * @var string
	 */
	private $type;

	/**
	 * Content type handler map
	 * @var array
	 */
	static private $types = [];

	/**
	 * Register a content type handler
	 */
	static function register(Handler $handler) {
		foreach ($handler->types() as $type) {
			self::$types[$type] = $handler;
		}
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
	 * API Version
	 *
	 * @param int $version
	 * @param string $type
	 */
	function __construct(int $version = 3, string $type = null) {
		$this->version = $version;
		if (isset($type)) {
			$this->setContentType($this->extractTypeFromParams(new Params($type)));
		}
	}

	/**
	 * @param Header $contentType
	 */
	function setContentTypeHeader(Header $contentType) {
		$this->type = $this->extractTypeFromHeader($contentType);
	}

	/**
	 * @param string $contentType
	 */
	function setContentType(string $contentType) {
		$this->type = $this->extractType($contentType);
	}

	/**
	 * @return int
	 */
	function getVersion() : int {
		return $this->version;
	}

	/**
	 * Get the (abbreviated) content type name
	 * @return string
	 */
	function getType() : string {
		return $this->type;
	}

	/**
	 * Get the (full) content type
	 * @return string
	 */
	function getContentType() : string {
		return $this->composeType($this->type);
	}

	/**
	 * @param API $api
	 * @return API clone
	 */
	function apply(API $api) : API {
		return $api->withHeader("Accept", $this->getContentType());
	}

	/**
	 * Decode a response message's body according to its content type
	 * @param Body $data
	 * @return mixed
	 * @throws UnexpectedValueException
	 */
	function decode(Body $data) {
		$type = $this->getType();
		if (static::registered($type)) {
			return self::$types[$type]->decode($data);
		}
		throw new UnexpectedValueException("Unhandled content type '$type'");
	}

	/**
	 * Encode a request message's body according to its content type
	 * @param mixed $data
	 * @return Body
	 * @throws UnexpectedValueException
	 */
	function encode($data) : Body {
		$type = $this->getType();
		if (static::registered($type)) {
			return self::$types[$type]->encode($data);
		}
		throw new UnexpectedValueException("Unhandled content type '$type'");
	}

	private function composeType(string $type) : string {
		$part = "[^()<>@,;:\\\"\/\[\]?.=[:space:][:cntrl:]]+";
		if (preg_match("/^$part\/$part\$/", $type)) {
			return $type;
		}

		switch (substr($type, 0, 1)) {
			case "+":
			case ".":
			case "":
				break;
			default:
				$type = ".$type";
				break;
		}
		return "application/vnd.github.v{$this->version}$type";
	}

	private function extractType(string $type) : string {
		return preg_replace("/
			(?:application\/(?:vnd\.github(?:\.v{$this->version})?)?)
			(?|
					\.					([^.+]+)
				|	(?:\.[^.+]+)?\+?	(json)
			)/x", "\\1", $type);
	}

	private function extractTypeFromParams(Params $params) : string {
		return $this->extractType(current(array_keys($params->params)));
	}

	private function extractTypeFromHeader(Header $header) : string {
		if (strcasecmp($header->name, "Content-Type")) {
			throw new InvalidArgumentException(
				"Expected Content-Type header, got ". $header->name);
		}
		return $this->extractTypeFromParams($header->getParams());
	}
}

ContentType::register(new Handler\Text);
ContentType::register(new Handler\Json);
ContentType::register(new Handler\Base64);
ContentType::register(new Handler\Stream);
