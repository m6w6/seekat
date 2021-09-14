<?php

namespace seekat;

use Countable;
use Generator;
use http\{Client, Client\Request, Message\Body, QueryString, Url};
use Iterator;
use IteratorAggregate;
use Psr\Log\{LoggerInterface, NullLogger};
use seekat\API\{Call, Consumer, ContentType, Future, Links};
use seekat\Exception\InvalidArgumentException;

class API implements IteratorAggregate, Countable {
	/**
	 * API version
	 * @var int
	 */
	private $version = 3;

	/**
	 * The current API endpoint URL
	 * @var Url
	 */
	private $url;

	/**
	 * Default headers to send out to the API endpoint
	 * @var array
	 */
	private $headers;

	/**
	 * Current endpoints links
	 * @var Links
	 */
	private $links;

	/**
	 * Current endpoint data's Content-Type
	 * @var API\ContentType
	 */
	private $type;

	/**
	 * Current endpoint's data
	 * @var array|object
	 */
	private $data;

	/**
	 * Logger
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Cache
	 * @var Call\Cache\Service
	 */
	private $cache;

	/**
	 * Promisor
	 * @var Future
	 */
	private $future;

	/**
	 * The HTTP client
	 * @var Client
	 */
	private $client;

	/**
	 * Create a new API endpoint root
	 *
	 * @codeCoverageIgnore
	 *
	 * @param Future $future pretending to fulfill promises
	 * @param array $headers Standard request headers, defaults to ["Accept" => "application/vnd.github.v3+json"]
	 * @param Url $url The API's endpoint, defaults to https://api.github.com
	 * @param Client $client The HTTP client to use for executing requests
	 * @param LoggerInterface $log A logger
	 * @param Call\Cache\Service $cache A cache
	 */
	function __construct(Future $future, array $headers = null, Url $url = null, Client $client = null, LoggerInterface $log = null, Call\Cache\Service $cache = null) {
		$this->future = $future;
		$this->cache = $cache;
		$this->logger = $log ?? new NullLogger;
		$this->url = $url ?? new Url("https://api.github.com");
		$this->client = $client ?? new Client;
		$this->type = new ContentType($this->version, "json");
		$this->headers = (array) $headers + [
			"Accept" => $this->type->getContentType()
		];
	}

	/**
	 * Ascend one level deep into the API endpoint
	 *
	 * @param string|int $seg The "path" element to ascend into
	 * @return API Endpoint clone referring to {$parent}/{$seg}
	 */
	function __get($seg) : API {
		if (substr($seg, -4) === "_url") {
			$url = new Url(uri_template($this->data->$seg));
			$that = $this->withUrl($url);
			$seg = basename($that->url->path);
		} else {
			$that = clone $this;
			$that->url->path .= "/".urlencode($seg);
			$this->exists($seg, $that->data);
		}

		$this->logger->debug("get($seg)", [
			"url" => [
				(string) $this->url,
				(string) $that->url
			],
			"data" => $that->data
		]);

		return $that;
	}

	/**
	 * Call handler that actually queues a data fetch and returns a promise
	 *
	 * @param string $method The API's "path" element to ascend into
	 * @param array $args Array of arguments forwarded to \seekat\API::get()
	 * @return mixed promise
	 */
	function __call(string $method, array $args) {
		/* We cannot implement an explicit then() method,
		 * because the Promise implementation might think
		 * we're actually implementing Thenable,
		 * which might cause an infinite loop.
		 */
		if ($method === "then"
		/*
		 * very short-hand version:
		 * ->users->m6w6->gists->get()->then(...)
		 * vs:
		 * ->users->m6w6->gists(...)
		 */
		||  is_callable(current($args))) {
			return $this->future->handlePromise($this->get(), ...$args);
		}

		return (new Call($this, $method))($args);
	}

	/**
	 * Run the send loop through a generator
	 *
	 * @param callable|Generator $cbg A \Generator or a factory of a \Generator yielding promises
	 * @return mixed The promise of the generator's return value
	 * @throws InvalidArgumentException
	 */
	function __invoke($cbg) {
		$this->logger->debug(__FUNCTION__);

		$consumer = new Consumer($this->getFuture(), function() {
				$this->client->send();
		});

		invoke:
		if ($cbg instanceof Generator) {
			return $consumer($cbg);
		}

		if (is_callable($cbg)) {
			$cbg = $cbg($this);
			goto invoke;
		}

		throw new InvalidArgumentException(
			"Expected callable or Generator, got ".typeof($cbg, true)
		);
	}

	/**
	 * Clone handler ensuring the underlying url will be cloned, too
	 */
	function __clone() {
		$this->url = clone $this->url;
	}

	/**
	 * The string handler for the endpoint's data
	 *
	 * @return string
	 */
	function __toString() : string {
		return (string) $this->type->encode($this->data);
	}

	/**
	 * Create an iterator over the endpoint's underlying data
	 *
	 * @return Iterator
	 */
	function getIterator() : Iterator {
		foreach ($this->data as $key => $cur) {
			if ($this->__get($key)->exists("url", $url)) {
				$url = new Url($url);
				$val = $this->withUrl($url)->withData($cur);
			} else {
				$val = $this->__get($key)->withData($cur);
			}
			yield $key => $val;
		}
	}

	/**
	 * Count the underlying data's entries
	 *
	 * @return int
	 */
	function count() : int {
		if (is_array($this->data)) {
			$count = count($this->data);
		} else if ($this->data instanceof Countable) {
			$count = count($this->data);
		} else if (is_object($this->data)) {
			$count = count((array) $this->data);
		} else {
			$count = 0;
		}
		$this->logger->debug("count()", [
			"of type" => typeof($this->data),
			"count" => $count
		]);
		return $count;
	}

	/**
	 * @return Url
	 */
	function getUrl() : Url {
		return $this->url;
	}

	/**
	 * @return LoggerInterface
	 */
	function getLogger() : LoggerInterface {
		return $this->logger;
	}

	/**
	 * @return Future
	 */
	function getFuture() {
		return $this->future;
	}

	/**
	 * @return Client
	 */
	public function getClient(): Client {
		return $this->client;
	}

	/**
	 * @return array|object
	 */
	function getData() {
		return $this->data;
	}

	/**
	 * Accessor to any hypermedia links
	 *
	 * @return null|Links
	 */
	function getLinks() {
		return $this->links;
	}

	/**
	 * @return int
	 */
	function getVersion() : int {
		return $this->version;
	}

	/**
	 * Export the endpoint's underlying data
	 *
	 * @return array ["url", "data", "type", "links", "headers"]
	 */
	function export() : array {
		$data = $this->data;
		$url = clone $this->url;
		$type = $this->type ? clone $this->type : null;
		$links = $this->links ? clone $this->links : null;
		$headers = $this->headers;
		return compact("url", "data", "type", "links", "headers");
	}

	/**
	 * @param $export
	 * @return API
	 */
	function with($export) : API {
		$that = clone $this;
		if (is_array($export) || ($export instanceof \ArrayAccess)) {
			isset($export["url"]) && $that->url = $export["url"];
			isset($export["data"]) && $that->data = $export["data"];
			isset($export["type"]) && $that->type = $export["type"];
			isset($export["links"]) && $that->links = $export["links"];
			isset($export["headers"]) && $that->headers = $export["headers"];
		}
		return $that;
	}

	/**
	 * Create a copy of the endpoint with specific data
	 *
	 * @param mixed $data
	 * @return API clone
	 */
	function withData($data) : API {
		$that = clone $this;
		$that->data = $data;
		return $that;
	}

	/**
	 * Create a copy of the endpoint with a specific Url, but with data reset
	 *
	 * @param Url $url
	 * @return API clone
	 */
	function withUrl(Url $url) : API {
		$that = clone $this;
		$that->url = $url;
		$that->data = null;
		#$that->links = null;
		return $that;
	}

	/**
	 * Create a copy of the endpoint with a specific header added/replaced
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return API clone
	 */
	function withHeader(string $name, $value) : API {
		$that = clone $this;
		if (isset($value)) {
			$that->headers[$name] = $value;
		} else {
			unset($that->headers[$name]);
		}
		return $that;
	}

	/**
	 * Create a copy of the endpoint with a customized accept header
	 *
	 * Changes the returned endpoint's accept header to "application/vnd.github.v3.{$type}"
	 *
	 * @param string $type The expected return data type, e.g. "raw", "html", ..., or a complete content type
	 * @param bool $keepdata Whether to keep already fetched data
	 * @return API clone
	 */
	function as(string $type, bool $keepdata = true) : API {
		$ct = new ContentType($this->version, $type);

		$that = $ct->apply($this);
		$that->type = $ct;

		if (!$keepdata) {
			$that->data = null;
		}
		return $that;
	}

	/**
	 * Perform a HEAD request against the endpoint's underlying URL
	 *
	 * @param mixed $args The HTTP query string parameters
	 * @param array $headers The request's additional HTTP headers
	 * @return mixed promise
	 */
	function head($args = null, array $headers = null, $cache = null) {
		return $this->request("HEAD", $args, null, $headers, $cache);
	}

	/**
	 * Perform a GET request against the endpoint's underlying URL
	 *
	 * @param mixed $args The HTTP query string parameters
	 * @param array $headers The request's additional HTTP headers
	 * @return mixed promise
	 */
	function get($args = null, array $headers = null, $cache = null) {
		return $this->request("GET", $args, null, $headers, $cache);
	}

	/**
	 * Perform a DELETE request against the endpoint's underlying URL
	 *
	 * @param mixed $args The HTTP query string parameters
	 * @param array $headers The request's additional HTTP headers
	 * @return mixed promise
	 */
	function delete($args = null, array $headers = null) {
		return $this->request("DELETE", $args, null, $headers);
	}

	/**
	 * Perform a POST request against the endpoint's underlying URL
	 *
	 * @param mixed $body The HTTP message's body
	 * @param mixed $args The HTTP query string parameters
	 * @param array $headers The request's additional HTTP headers
	 * @return mixed promise
	 */
	function post($body = null, $args = null, array $headers = null) {
		return $this->request("POST", $args, $body, $headers);
	}

	/**
	 * Perform a PUT request against the endpoint's underlying URL
	 *
	 * @param mixed $body The HTTP message's body
	 * @param mixed $args The HTTP query string parameters
	 * @param array $headers The request's additional HTTP headers
	 * @return mixed promise
	 */
	function put($body = null, $args = null, array $headers = null) {
		return $this->request("PUT", $args, $body, $headers);
	}

	/**
	 * Perform a PATCH request against the endpoint's underlying URL
	 *
	 * @param mixed $body The HTTP message's body
	 * @param mixed $args The HTTP query string parameters
	 * @param array $headers The request's additional HTTP headers
	 * @return mixed promise
	 */
	function patch($body = null, $args = null, array $headers = null) {
		return $this->request("PATCH", $args, $body, $headers);
	}

	/**
	 * Perform all queued HTTP transfers
	 *
	 * @return API self
	 */
	function send() : API {
		$this->logger->debug("send: start loop");
		while (count($this->client)) {
			$this->client->send();
		}
		$this->logger->debug("send: end loop");
		return $this;
	}

	/**
	 * Check for a specific key in the endpoint's underlying data
	 *
	 * @param string $seg
	 * @param mixed &$val
	 * @return bool
	 */
	function exists($seg, &$val = null) : bool {
		if (is_array($this->data) && array_key_exists($seg, $this->data)) {
			$val = $this->data[$seg];
			$exists = true;
		} elseif (is_object($this->data) && property_exists($this->data, $seg)) {
			$val = $this->data->$seg;
			$exists = true;
		} else {
			$val = null;
			$exists = false;
		}

		$this->logger->debug(sprintf("exists(%s) in %s -> %s",
			$seg, typeof($this->data, false), $exists ? "true" : "false"
		), [
			"url" => (string) $this->url,
			"val" => typeof($val, false),
		]);

		return $exists;
	}

	/**
	 * Queue the actual HTTP transfer through \seekat\API\Deferred and return the promise
	 *
	 * @param string $method The HTTP request method
	 * @param mixed $args The HTTP query string parameters
	 * @param mixed $body Thee HTTP message's body
	 * @param array $headers The request's additional HTTP headers
	 * @param Call\Cache\Service $cache
	 * @return mixed promise
	 */
	private function request(string $method, $args = null, $body = null, array $headers = null, Call\Cache\Service $cache = null) {
		if (isset($this->data)) {
			$this->logger->debug("request -> resolve", [
				"method"  => $method,
				"url"     => (string) $this->url,
				"args"    => $args,
				"body"    => $body,
				"headers" => $headers,
			]);

			return Future\resolve($this->future, $this);
		}

		$url = $this->url->mod(["query" => new QueryString($args)]);
		$request = new Request($method, $url, ((array) $headers) + $this->headers,
			 $body = $this->type->encode(is_resource($body) ? new Body($body) : $body));

		$this->logger->info("request -> deferred", [
			"method" => $method,
			"url" => (string) $this->url,
			"args" => $this->url->query,
			"body" => $body,
			"headers" => $headers,
		]);

		return (new Call\Deferred($this, $request, $cache ?: $this->cache))();
	}
}
