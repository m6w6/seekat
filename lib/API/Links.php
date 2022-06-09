<?php

namespace seekat\API;

use http\{Header, Params, QueryString, Url};
use seekat\Exception\UnexpectedValueException;

final class Links {
	/**
	 * Parsed "Link" relations
	 * @var array<string, Url>
	 */
	private $relations = [];

	/**
	 * Parse the hypermedia link header
	 *
	 * @param ?Header $links The Link header
	 * @throws UnexpectedValueException
	 */
	function __construct(Header $links = null) {
		if ($links) {
			if (strcasecmp($links->name, "Link")) {
				throw new UnexpectedValueException("Expected 'Link' header, got: '{$links->name}'");
			}
			$params = new Params($links->value, ",", ";", "=",
				Params::PARSE_RFC5988 | Params::PARSE_ESCAPED);
			if ($params->params) {
				foreach ($params->params as $link => $param) {
					$this->relations[$param["arguments"]["rel"]] = new Url($link);
				}
			}
		}
	}

	/**
	 * Receive the link header's parsed relations
	 *
	 * @return array<string, Url>
	 */
	function getRelations() : array {
		return $this->relations;
	}

	/**
	 * Get the URL of the link's "next" relation
	 *
	 * Returns the link's "last" relation if it exists and "next" is not set.
	 */
	function getNext() : ?Url {
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
	 */
	function getPrev() : ?Url {
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
	 */
	function getLast() : ?Url {
		if (isset($this->relations["last"])) {
			return $this->relations["last"];
		}
		return null;
	}

	/**
	 * Get the URL of the link's "first" relation
	 */
	function getFirst() : ?Url {
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
	function getPage($which) : int {
		if (($link = $this->{"get$which"}())) {
			$url = new Url($link, null, 0);
			$qry = new QueryString($url->query);
			return $qry->getInt("page", 1);
		}
		return 1;
	}
}
