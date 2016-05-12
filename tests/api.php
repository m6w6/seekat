<?php

use seekat\API;
use React\Promise\PromiseInterface;

describe("API", function() {

	it("should return API on property access", function() {
		expect($this->api->users)->to->be->instanceof(API::class);
	});

	it("should return a clone on property access", function() {
		expect($this->api->users)->to->not->equal($this->api);
	});

	it("should return PromiseInterface on function call", function() {
		expect($this->api->users->m6w6())->to->be->instanceof(PromiseInterface::class);
	});

	it("should successfully request /users/m6w6", function() {
		$this->api->users->m6w6()->then(function($json) use(&$m6w6) {
			$m6w6 = $json;
		})->otherwise(function($error) use(&$errors) {
			$errors[] = (string) $error;
		});

		$this->api->send();

		expect($errors)->to->be->empty;
		expect($m6w6->login)->to->loosely->equal("m6w6");
	});

	it("should return the count of followers when accessing /users/m6w6->followers", function() {
		$this->api->users->m6w6()->then(function($m6w6) use(&$followers) {
			$followers = $m6w6->followers;
		})->otherwise(function($error) use(&$errors) {
			$errors[] = (string) $error;
		});

		$this->api->send();

		expect($errors)->to->be->empty();
		expect($followers->export())->to->be->an("integer");
	});

	it("should fetch followers_url when accessing /users/m6w6->followers_url", function() {
		$this->api->users->m6w6()->then(function($m6w6) use(&$followers, &$errors) {
			$m6w6->followers_url()->then(function($json) use(&$followers) {
				$followers = $json;
			})->otherwise(function($error) use(&$errors) {
				$errors[] = (string) $error;
			});
		})->otherwise(function($error) use(&$errors) {
			$errors[] = (string) $error;
		});

		$this->api->send();

		expect($errors)->to->be->empty;
		expect($followers->export())->to->be->an("array");
		expect(count($followers))->to->be->above(0);
	});

	it("should handle a few requests in parallel", function() {
		$this->api->users->m6w6()->then(function($m6w6) use(&$count, &$errors) {
			foreach ($m6w6 as $key => $val) {
				switch ($key) {
					case "html_url":
					case "avatar_url":
						break;
					default:
						if (substr($key, -4) === "_url") {
							$val->get()->then(function() use(&$count) {
								++$count;
							})->otherwise(function($error) use(&$errors) {
								$errors[] = (string) $error;
							});
						}
				}
			}
		})->otherwise(function($error) use(&$errors) {
			$errors[] = (string) $error;
		});

		$this->api->send();

		expect($errors)->to->be->empty;
		expect($count)->to->be->above(2);
	});
});
