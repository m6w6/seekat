<?php

namespace seekat\API;

use http\Header;
use http\Params;
use http\QueryString;
use http\Url;

class Links implements \Serializable
{
	/**
	 * Parsed "Link" relations
	 * @var \http\Params
	 */
	private $params;
	
	/**
	 * Parsed "Link" relations
	 * @var array
	 */
	private $relations = [];

	/**
	 * Parse the hypermedia link header
	 *
	 * @var string $header_value The value of the "Link" header
	 */
	function __construct(Header $links) {
		if (strcasecmp($links->name, "Link")) {
			throw new \UnexpectedValueException("Expected 'Link' header, got: '{$links->name}'");
		}
		$this->unserialize($links->value);
	}

	function __toString() : string {
		return $this->serialize();
	}

	function serialize() {
		return (string) $this->params;
	}

	function unserialize($links) {
		$this->params = new Params($links, ",", ";", "=",
			Params::PARSE_RFC5988 | Params::PARSE_ESCAPED);
		if ($this->params->params) {
			foreach ($this->params->params as $link => $param) {
				$this->relations[$param["arguments"]["rel"]] = new Url($link);
			}
		}
	}

	/**
	 * Receive the link header's parsed relations
	 *
	 * @return array
	 */
	function getRelations() : array {
		return $this->relations;
	}

	/**
	 * Get the URL of the link's "next" relation
	 *
	 * Returns the link's "last" relation if it exists and "next" is not set.
	 *
	 * @return \http\Url
	 */
	function getNext() {
		if (isset($this->relations["next"])) {
			return $this->relations["next"];
		}
		if (isset($this->relations["last"])) {
			return $this->relations["last"];
		}
		return null;
	}

	/**
	 * Get the URL of the link's "prev" relation
	 *
	 * Returns the link's "first" relation if it exists and "prev" is not set.
	 *
	 * @return \http\Url
	 */
	function getPrev() {
		if (isset($this->relations["prev"])) {
			return $this->relations["prev"];
		}
		if (isset($this->relations["first"])) {
			return $this->relations["first"];
		}
		return null;
	}

	/**
	 * Get the URL of the link's "last" relation
	 *
	 * @return \http\Url
	 */
	function getLast() {
		if (isset($this->relations["last"])) {
			return $this->relations["last"];
		}
		return null;
	}

	/**
	 * Get the URL of the link's "first" relation
	 *
	 * @return \http\Url
	 */
	function getFirst() {
		if (isset($this->relations["first"])) {
			return $this->relations["first"];
		}
		return null;
	}

	/**
	 * Get the page sequence of the current link's relation
	 *
	 * @param string $which The relation of which to extract the page
	 * @return int The current page sequence
	 */
	function getPage($which) {
		if (($link = $this->{"get$which"}())) {
			$url = new Url($link, null, 0);
			$qry = new QueryString($url->query);
			return $qry->getInt("page", 1);
		}
		return 1;
	}
}
