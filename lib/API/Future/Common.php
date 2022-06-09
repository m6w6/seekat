<?php

namespace seekat\API\Future;

use seekat\API\Future;
use seekat\Exception\UnexpectedValueException;

abstract class Common implements Future {
	protected \WeakMap $cancellations;
	protected string $promiseType;

	public function __construct() {
		if (empty($this->promiseType)) {
			throw new UnexpectedValueException("Promise type must be set in Future implementation");
		}
		$this->cancellations = new \WeakMap;
	}

	function isPromise(object $promise): bool {
		return $promise instanceof $this->promiseType;
	}

	function getPromise(object $context) : object {
		return $context->promise();
	}

	function cancelPromise(object $promise) : void {
		if (isset($this->cancellations[$promise])) {
			$this->cancellations[$promise]();
		}
	}

	function resolve(mixed $value) : object {
		$context = $this->createContext();
		$promise = $context->promise();
		$context->resolve($value);
		return $promise;
	}

	function resolver(object $context) : \Closure {
		return function($value) use($context) {
			$context->resolve($value);
		};
	}

	function reducer() : \Closure {
		return function(array $promises) {
			return $this->all($promises);
		};
	}

}
