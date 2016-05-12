<?php

namespace seekat;

use seekat\API\ContentType;
use seekat\Exception\RequestException;

use http\Url;
use http\Header;
use http\Client;
use http\Client\Request;
use http\Client\Response;
use http\Message\Body;
use http\QueryString;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use React\Promise\ExtendedPromiseInterface;
use function React\Promise\resolve;
use function React\Promise\reject;

class API implements \IteratorAggregate, \Countable {
	/**
	 * The current API endpoint URL
	 * @var \http\Url
	 */
	private $__url;
	
	/**
	 * Logger
	 * @var \Psr\Log\LoggerInterface
	 */
	private $__log;
	
	/**
	 * The HTTP client
	 * @var \http\Client
	 */
	private $__client;
	
	/**
	 * Default headers to send out to the API endpoint
	 * @var array
	 */
	private $__headers;
	
	/**
	 * Current endpoint data's Content-Type
	 * @var \http\Header
	 */
	private $__type;
	
	/**
	 * Current endpoint's data
	 * @var array|object
	 */
	private $__data;
	
	/**
	 * Current endpoints links
	 * @var seekat\API\Links
	 */
	private $__links;
	
	/**
	 * Create a new API endpoint root
	 *
	 * @var array $headers Standard request headers, defaults to ["Accept" => "application/vnd.github.v3+json"]
	 * @var \http\Url The API's endpoint, defaults to https://api.github.com
	 * @var \http\Client $client The HTTP client to use for executing requests
	 * @var \Psr\Log\LoggerInterface $log A logger
	 */
	function __construct(array $headers = null, Url $url = null, Client $client = null, LoggerInterface $log = null) {
		$this->__log = $log ?? new NullLogger;
		$this->__url = $url ?? new Url("https://api.github.com");
		$this->__client = $client ?? new Client;
		$this->__headers = (array) $headers + [
		"Accept" => "application/vnd.github.v3+json"
		];
	}
	
	/**
	 * Ascend one level deep into the API endpoint
	 *
	 * @var string|int $seg The "path" element to ascend into
	 * @return \seekat\API Endpoint clone referring to {$parent}/{$seg}
	 */
	function __get($seg) : API {
		if (substr($seg, -4) === "_url") {
			$url = new Url(uri_template($this->__data->$seg));
			$that = $this->withUrl($url);
			$seg = basename($that->__url->path);
		} else {
			$that = clone $this;
			$that->__url->path .= "/".urlencode($seg);
			$this->exists($seg, $that->__data);
		}
		
		$this->__log->debug(__FUNCTION__."($seg)", [
			"url" => [
				(string) $this->__url,
				(string) $that->__url
			],
		]);
		
		return $that;
	}
	
	/**
	 * Call handler that actually queues a data fetch and returns a promise
	 *
	 * @var string $method The API's "path" element to ascend into
	 * @var array $args Array of arguments forwarded to \seekat\API::get()
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function __call(string $method, array $args) : ExtendedPromiseInterface {
		/* We cannot implement an explicit then() method,
		 * because the Promise implementation might think
		 * we're actually implementing Thenable,
		 * which might cause an infite loop.
		 */
		if ($method === "then") {
			return $this->get()->then(...$args);
		}
		
		/*
		 * very short-hand version:
		 * ->users->m6w6->gists->get()->then(...)
		 * vs:
		 * ->users->m6w6->gists(...)
		 */
		if (is_callable(current($args))) {
			return $this->$method->get()->then(current($args));
		}
		
		/* standard access */
		if ($this->exists($method)) {
			return $this->$method->get(...$args);
		}
		
		/* fetch resource, unless already localized, and try for {$method}_url */
		return $this->$method->get(...$args)->otherwise(function($error) use($method, $args) {
			if ($this->exists($method."_url", $url)) {
				
				$this->__log->info(__FUNCTION__."($method): ". $error->getMessage(), [
					"url" => (string) $this->__url
				]);
				
				$url = new Url(uri_template($url, (array) current($args)));
				return $this->withUrl($url)->get(...$args);
			}
			
			$this->__log->error(__FUNCTION__."($method): ". $error->getMessage(), [
				"url" => (string) $this->__url
			]);
			
			throw $error;
		});
	}
	
	/**
	 * Clone handler ensuring the underlying url will be cloned, too
	 */
	function __clone() {
		$this->__url = clone $this->__url;
	}
	
	/**
	 * The string handler for the endpoint's data
	 *
	 * @return string
	 */
	function __toString() : string {
		if (is_scalar($this->__data)) {
			return (string) $this->__data;
		}
		
		/* FIXME */
		return json_encode($this->__data);
	}
	
	/**
	 * Import handler for the endpoint's underlying data
	 *
	 * \seekat\Deferred will call this when the request will have finished.
	 *
	 * @var \http\Client\Response $response
	 * @return \seekat\API self
	 */
	function import(Response $response) : API {
		//addcslashes($response, "\0..\40\42\47\134\140\177..\377")
		
		$this->__log->info(__FUNCTION__.": ". $response->getInfo(), [
			"url" => (string) $this->__url
		]);
		
		if ($response->getResponseCode() >= 400) {
			$e = new RequestException($response);
			
			$this->__log->critical(__FUNCTION__.": ".$e->getMessage(), [
				"url" => (string) $this->__url,
			]);
			
			throw $e;
		}
		
		if (!($type = $response->getHeader("Content-Type", Header::class))) {
			$e = new RequestException($response);
			$this->__log->error(
				__FUNCTION__.": Empty Content-Type -> ".$e->getMessage(), [
				"url" => (string) $this->__url,
			]);
			throw $e;
		}
		
		try {
			$this->__type = new ContentType($type);
			$this->__data = $this->__type->parseBody($response->getBody());
			
			if (($link = $response->getHeader("Link", Header::class))) {
				$this->__links = new API\Links($link);
			}
		} catch (\Exception $e) {
			$this->__log->error(__FUNCTION__.": ".$e->getMessage(), [
				"url" => (string) $this->__url
			]);
			
			throw $e;
		}
		
		return $this;
	}
	
	/**
	 * Export the endpoint's underlying data
	 *
	 * @return mixed
	 */
	function export(&$type = null) {
		$type = clone $this->__type;
		return $this->__data;
	}
	
	/**
	 * Create a copy of the endpoint with specific data
	 *
	 * @var mixed $data
	 * @return \seekat\API clone
	 */
	function withData($data) : API {
		$that = clone $this;
		$that->__data = $data;
		return $that;
	}
	
	/**
	 * Create a copy of the endpoint with a specific Url, but with data reset
	 *
	 * @var \http\Url $url
	 * @return \seekat\API clone
	 */
	function withUrl(Url $url) : API {
		$that = $this->withData(null);
		$that->__url = $url;
		return $that;
	}
	
	/**
	 * Create a copy of the endpoint with a specific header added/replaced
	 *
	 * @var string $name
	 * @var mixed $value
	 * @return \seekat\API clone
	 */
	function withHeader(string $name, $value) : API {
		$that = clone $this;
		if (isset($value)) {
			$that->__headers[$name] = $value;
		} else {
			unset($that->__headers[$name]);
		}
		return $that;
	}
	
	/**
	 * Create a copy of the endpoint with a customized accept header
	 *
	 * Changes the returned endpoint's accept header to
 * 	"application/vnd.github.v3.{$type}"
	 *
	 * @var string $type The expected return data type, e.g. "raw", "html", etc.
	 * @var bool $keepdata Whether to keep already fetched data
	 * @return \seekat\API clone
	 */
	function as(string $type, bool $keepdata = true) : API {
		switch(substr($type, 0, 1)) {
		case "+":
		case ".":
		case "":
			break;
		default:
			$type = ".$type";
			break;
		}
		$vapi = ContentType::version();
		$that = $this->withHeader("Accept", "application/vnd.github.v$vapi$type");
		if (!$keepdata) {
			$that->__data = null;
		}
		return $that;
	}
	
	/**
	 * Create an iterator over the endpoint's underlying data
	 *
	 * @return \seekat\API\Iterator
	 */
	function getIterator() : API\Iterator {
		return new API\Iterator($this);
	}
	
	/**
	 * Count the underlying data's entries
	 *
	 * @return int
	 */
	function count() : int {
		return count($this->__data);
	}
	
	/**
	 * Perform a GET request against the endpoint's underlying URL
	 *
	 * @var mixed $args The HTTP query string parameters
	 * @var array $headers The request's additional HTTP headers
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function get($args = null, array $headers = null) : ExtendedPromiseInterface {
		return $this->__xfer("GET", $args, null, $headers);
	}
	
	/**
	 * Perform a DELETE request against the endpoint's underlying URL
	 *
	 * @var mixed $args The HTTP query string parameters
	 * @var array $headers The request's additional HTTP headers
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function delete($args = null, array $headers = null) : ExtendedPromiseInterface {
		return $this->__xfer("DELETE", $args, null, $headers);
	}
	
	/**
	 * Perform a POST request against the endpoint's underlying URL
	 *
	 * @var mixed $body The HTTP message's body
	 * @var mixed $args The HTTP query string parameters
	 * @var array $headers The request's additional HTTP headers
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function post($body = null, $args = null, array $headers = null) : ExtendedPromiseInterface {
		return $this->__xfer("POST", $args, $body, $headers);
	}
	
	/**
	 * Perform a PUT request against the endpoint's underlying URL
	 *
	 * @var mixed $body The HTTP message's body
	 * @var mixed $args The HTTP query string parameters
	 * @var array $headers The request's additional HTTP headers
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function put($body = null, $args = null, array $headers = null) : ExtendedPromiseInterface {
		return $this->__xfer("PUT", $args, $body, $headers);
	}
	
	/**
	 * Perform a PATCH request against the endpoint's underlying URL
	 *
	 * @var mixed $body The HTTP message's body
	 * @var mixed $args The HTTP query string parameters
	 * @var array $headers The request's additional HTTP headers
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function patch($body = null, $args = null, array $headers = null) : ExtendedPromiseInterface {
		return $this->__xfer("PATCH", $args, $body, $headers);
	}
	
	/**
	 * Accessor to any hypermedia links
	 *
	 * @return null|\seekat\API\Links
	 */
	function links() {
		return $this->__links;
	}
	
	/**
	 * Perform a GET request against the link's "first" relation
	 *
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function first() : ExtendedPromiseInterface {
		if ($this->links() && ($first = $this->links()->getFirst())) {
			return $this->withUrl($first)->get();
		}
		return reject($this->links());
	}
	
	/**
	 * Perform a GET request against the link's "prev" relation
	 *
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function prev() : ExtendedPromiseInterface {
		if ($this->links() && ($prev = $this->links()->getPrev())) {
			return $this->withUrl($prev)->get();
		}
		return reject($this->links());
	}
	
	/**
	 * Perform a GET request against the link's "next" relation
	 *
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function next() : ExtendedPromiseInterface {
		if ($this->links() && ($next = $this->links()->getNext())) {
			return $this->withUrl($next)->get();
		}
		return reject($this->links());
	}
	
	/**
	 * Perform a GET request against the link's "last" relation
	 *
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	function last() : ExtendedPromiseInterface {
		if ($this->links() && ($last = $this->links()->getLast())) {
			return $this->withUrl($last)->get();
		}
		return reject($this->links());
	}
	
	/**
	 * Perform all queued HTTP transfers
	 *
	 * @return \seekat\API self
	 */
	function send() : API {
		$this->__log->debug(__FUNCTION__.": start loop");
		while (count($this->__client)) {
			$this->__client->send();
		}
		$this->__log->debug(__FUNCTION__.": end loop");
		return $this;
	}
	
	/**
	 * Run the send loop once
	 *
	 * @param callable $timeout as function(\seekat\API $api) : float, returning any applicable select timeout
	 * @return bool
	 */
	function __invoke(callable $timeout = null) : bool {
		$this->__log->debug(__FUNCTION__);
		
		if (count($this->__client)) {
			if ($this->__client->once()) {
				if ($timeout) {
					$timeout = $timeout($this);
				}
				
				$this->__log->debug(__FUNCTION__.": wait", compact("timeout"));
				
				$this->__client->wait($timeout);
				return 0 < count($this->__client);
			}
		}
		return false;
	}
	
	/**
	 * Check for a specific key in the endpoint's underlying data
	 *
	 * @var string $seg
	 * @var mixed &$val
	 * @return bool
	 */
	function exists($seg, &$val = null) : bool {
		if (is_array($this->__data) && array_key_exists($seg, $this->__data)) {
			$val = $this->__data[$seg];
			$exists = true;
		} elseif (is_object($this->__data) && property_exists($this->__data, $seg)) {
			$val = $this->__data->$seg;
			$exists = true;
		} else {
			$val = null;
			$exists = false;
		}
		
		$this->__log->debug(__FUNCTION__."($seg) in ".(
			is_object($this->__data)
				? get_class($this->__data)
				: gettype($this->__data)
		)." -> ".(
			$exists
				? "true"
				: "false"
		), [
			"url" => (string) $this->__url,
			"val" => $val,
		]);
		
		return $exists;
	}
	
	/**
	 * Queue the actual HTTP transfer through \seekat\API\Deferred and return the promise
	 *
	 * @var string $method The HTTP request method
	 * @var mixed $args The HTTP query string parameters
	 * @var mixed $body Thee HTTP message's body
	 * @var array $headers The request's additional HTTP headers
	 * @return \React\Promise\ExtendedPromiseInterface
	 */
	private function __xfer(string $method, $args = null, $body = null, array $headers = null) : ExtendedPromiseInterface {
		if (isset($this->__data)) {
			$this->__log->debug(__FUNCTION__."($method) -> resolve", [
				"url" => (string) $this->__url,
				"args" => $args,
				"body" => $body,
				"headers" => $headers,
			]);
			
			return resolve($this);
		}
		
		$url = $this->__url->mod(["query" => new QueryString($args)]);
		$request = new Request($method, $url, ((array) $headers) + $this->__headers,
			 $body = is_array($body) ? json_encode($body) : (
				is_resource($body) ? new Body($body) : (
					is_scalar($body) ? (new Body)->append($body) :
						$body)));
		
		$this->__log->info(__FUNCTION__."($method) -> request", [
			"url" => (string) $this->__url,
			"args" => $this->__url->query,
			"body" => $body,
			"headers" => $headers,
		]);
		
		return (new API\Deferred($this, $this->__client, $request))->promise();
	}
}
