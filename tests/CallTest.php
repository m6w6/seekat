<?php

use seekat\API;

class CallTest extends BaseTest
{
	use ConsumePromise;
	use AssertSuccess;

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testResolvesPromiseWithResultAfterCallCompletes($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$this->assertInstanceOf(API::class, $m6w6);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testExportsArrayWithKeysDataAndUrlAndTypeAndLinksAndHeaders($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$export = $m6w6->export();
		$this->assertArrayHasKey("data", $export);
		$this->assertArrayHasKey("url", $export);
		$this->assertArrayHasKey("type", $export);
		$this->assertArrayHasKey("links", $export);
		$this->assertArrayHasKey("headers", $export);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testFetchedDataIsAccessibleOnPropertyAccess($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$this->assertEquals("m6w6", $m6w6->login);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testFetchedDataIsAccessibleOnPropertyAccessDespiteUrlSuffixAvailable($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$this->assertGreaterThan(0, (int) (string) $m6w6->followers);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testFetchUrlSuffix($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$followers = $this->assertSuccess($api->users->m6w6->followers);
		$data = $followers->export()["data"];
		$this->assertInternalType("array", $data);
		$this->assertInternalType("object", $data[0]);
		$this->assertInternalType("object", $followers->{0});
		$this->assertGreaterThan(30, (string) $m6w6->followers);
		$this->assertGreaterThan(0, count($followers));
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testFetchExplicitUrlSuffix($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$followers = $this->assertSuccess($m6w6->followers_url);
		$data = $followers->export()["data"];
		$this->assertInternalType("array", $data);
		$this->assertInternalType("object", $data[0]);
		$this->assertInternalType("object", $followers->{0});
		$this->assertGreaterThan(30, (string) $m6w6->followers);
		$this->assertGreaterThan(0, count($followers));
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testFetchImplicitUrlSuffix($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$promise = $m6w6->followers();
		$this->consumePromise($promise, $errors, $results);
		$api->send();
		$this->assertEmpty($errors);
		$this->assertNotEmpty($results);
		$followers = $results[0];
		$this->assertInstanceOf(API::class, $followers);
		$data = $followers->export()["data"];
		$this->assertInternalType("array", $data);
		$this->assertGreaterThan(0, count($data));
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testFetchParallelFromIterator($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		foreach ($m6w6 as $key => $val) {
			switch ($key) {
				case "html_url":
				case "avatar_url":
					break;
				default:
					if (substr($key, -4) === "_url") {
						$batch[] = $val;
					}
			}
		}
		$results = $this->assertAllSuccess($batch);
		$this->assertGreaterThan(2, $results);
	}
}
