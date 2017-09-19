<?php

use seekat\API\Links;

class GeneratorTest extends BaseTest
{
	use ConsumePromise;

	/**
	 * @group testdox
	 * @dataProvider provideApi
	 */
	function testIteratesOverAGeneratorOfPromises($api) {
		$api(function($api) use(&$gists_count, &$files_count) {
			$gists = yield $api->users->m6w6->gists();
			$gists_count = count($gists);
			foreach ($gists as $gist) {
				$files_count += count($gist->files);
			}
		});
		$this->assertGreaterThan(0, $gists_count);
		$this->assertGreaterThanOrEqual($gists_count, $files_count);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testIteratesOverAGeneratorOfPromisesUsingLinks($api) {
		$promise = $api(function($api) use(&$repos, &$first, &$next, &$last, &$prev) {
			$repos = yield $api->users->m6w6->repos(["per_page" => 1]);
			$last = yield Links\last($repos);
			$prev = yield Links\prev($last);
			$next = yield Links\next($prev);
			$first = yield Links\first($prev);
			return -123;
		});

		$this->consumePromise($promise, $errors, $results);
		$this->assertEmpty($errors);
		$this->assertEquals([-123], $results);

		$first_data = $first->export()["data"];
		$next_data = $next->export()["data"];
		$last_data = $last->export()["data"];
		$repos_data = $repos->export()["data"];

		$this->assertEquals($repos_data, $first_data);
		$this->assertEquals($last_data, $next_data);
	}
}
