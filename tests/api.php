<?php

use seekat\API;
use React\Promise\PromiseInterface;
use seekat\API\Links\ {
	function first, function last, function next, function prev
};

describe("API", function() {

	describe("Interface", function() {
		it("should return API on property access", function() {
			expect($this->api->users)->to->be->instanceof(API::class);
		});

		it("should return a clone on property access", function() {
			expect($this->api->users)->to->not->equal($this->api);
		});

		it("should return PromiseInterface on function call", function() {
			expect($this->api->users->m6w6())->to->be->instanceof(PromiseInterface::class);
		});
	});

	describe("Requests", function() {

		it("should successfully request /users/m6w6", function() {
			$this->api->users->m6w6()->then(function($json) use(&$m6w6) {
				$m6w6 = $json;
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty;
			expect($m6w6->login)->to->loosely->equal("m6w6");
		});

		it("should export an array of data, url, type, links and headers", function() {
			$this->api->users->m6w6()->then(function($json) use(&$m6w6) {
				$m6w6 = $json;
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty();
			expect($m6w6->export())->to->be->an("array")->and->contain->keys([
				"data", "url", "type", "links", "headers"
			]);
		});

		it("should return the count of followers when accessing /users/m6w6->followers", function() {
			$this->api->users->m6w6()->then(function($m6w6) use(&$followers) {
				$followers = $m6w6->followers;
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty();
			expect($followers->export()["data"])->to->be->an("integer");
		});

		it("should fetch followers_url when accessing /users/m6w6->followers_url", function() {
			$this->api->users->m6w6()->then(function($m6w6) use(&$followers, &$errors) {
				$m6w6->followers_url()->then(function($json) use(&$followers) {
					$followers = $json;
				}, function($error) use(&$errors) {
					$errors[] = (string) $error;
				});
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty;
			expect($followers->export()["data"])->to->be->an("array");
			expect(count($followers))->to->be->above(0);
		});

		it("should provide access to array indices", function() {
			$this->api->users->m6w6()->then(function($m6w6) use(&$followers, &$errors) {
				$m6w6->followers_url()->then(function($json) use(&$followers) {
					$followers = $json;
				}, function($error) use(&$errors) {
					$errors[] = (string) $error;
				});
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty;
			expect($followers->{0})->to->be->an("object");
			expect($followers->export()["data"][0])->to->be->an("object");
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
								}, function($error) use(&$errors) {
									$errors[] = (string) $error;
								});
							}
					}
				}
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty;
			expect($count)->to->be->above(2);
		});

	});

	describe("Cache", function() {
		it("should cache successive calls", function() {
			$cache = new API\Call\Cache\Service\Hollow();
			$this->api->users->m6w6(null, null, $cache)->then(function($json) use(&$m6w6) {
				$m6w6 = $json;
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			$data = $cache->getStorage();
			$this->api->users->m6w6(null, null, $cache)->then(function($json) use(&$m6w6_) {
				$m6w6_ = $json;
			}, function($error) use(&$errors) {
				$errors[] = (string) $error;
			});

			$this->api->send();

			expect($errors)->to->be->empty;
			expect($m6w6->login)->to->loosely->equal("m6w6");
			expect($m6w6_->login)->to->loosely->equal("m6w6");
			expect($data)->to->equal($cache->getStorage());
			expect(count($cache->getStorage()))->to->equal(1);
		});
		xit("should refresh stale cache entries");
	});

	describe("Generators", function() {
		it("should iterate over a generator of promises", function() {
			($this->api)(function($api) use(&$gists_count, &$files_count) {
				$gists = yield $api->users->m6w6->gists();
				$gists_count = count($gists);
				foreach ($gists as $gist) {
					$files_count += count($gist->files);
				}
			});
			expect($gists_count)->to->be->above(0);
			expect($files_count)->to->be->at->least($gists_count);
		});
		it("should iterate over a generator of promises with links", function() {
			($this->api)(function($api) use(&$repos, &$first, &$next, &$last, &$prev) {
				$repos = yield $api->users->m6w6->repos(["per_page" => 1]);
				$last = yield last($repos);
				$prev = yield prev($last);
				$next = yield next($prev);
				$first = yield first($prev);
				return -123;
			})->done(function($value) use(&$result) {
				$result = $value;
			});

			expect($result)->to->equal(-123);
			expect($repos->export()["data"])->to->loosely->equal($first->export()["data"]);
			expect($last->export()["data"])->to->loosely->equal($next->export()["data"]);
		});
	});

	describe("Errors", function() {
		it("should handle cancellation gracefully", function() {
			$this->api->users->m6w6()->then(function($value) use(&$result) {
				$result = $value;
			}, function($error) use(&$message) {
				$message = \seekat\Exception\message($error);
			})->cancel();
			expect($result)->to->be->empty();
			expect($message)->to->equal("Cancelled");
		});

		it("should handle request errors gracefully", function() {
			$this->api->generate->a404()->then(function($value) use(&$result) {
				$result = $value;
			}, function($error) use(&$message) {
				$message = \seekat\Exception\message($error);
			});
			$this->api->send();
			expect($result)->to->be->empty();
			expect($message)->to->equal("Not Found");
		});
	});
});
