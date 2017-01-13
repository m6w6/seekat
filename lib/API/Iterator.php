<?php

namespace seekat\API;

use http\Url;
use Iterator as BaseIterator;
use seekat\API;

class Iterator implements BaseIterator
{
	/**
	 * The endpoint
	 * @var API
	 */
	private $api;

	/**
	 * The iterator's data
	 * @var array
	 */
	private $data;

	/**
	 * The current key
	 * @var int|string
	 */
	private $key;

	/**
	 * The current data entry
	 * @var mixed
	 */
	private $cur;

	/**
	 * Create a new iterator over $data returning \seekat\API instances
	 *
	 * @var API $api The endpoint
	 * @var array|object $data
	 */
	function __construct(API $api) {
		$this->api = $api;
		$this->data = (array) $api->export()["data"];
	}

	/**
	 * Get the current key
	 *
	 * @return int|string
	 */
	function key() {
		return $this->key;
	}

	/**
	 * Get the current data entry
	 *
	 * @return API
	 */
	function current() {
		return $this->cur;
	}

	function next() {
		if (list($key, $cur) = each($this->data)) {
			$this->key = $key;
			if ($this->api->$key->exists("url", $url)) {
				$url = new Url($url);
				$this->cur = $this->api->withUrl($url)->withData($cur);
			} else {
				$this->cur = $this->api->$key->withData($cur);
			}
		} else {
			$this->key = null;
			$this->cur = null;
		}
	}

	function valid() {
		return isset($this->cur);
	}

	function rewind() {
		if (is_array($this->data)) {
			reset($this->data);
			$this->next();
		}
	}
}
