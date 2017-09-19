<?php

use AsyncInterop\Promise;
use seekat\API;

class APITest extends BaseTest
{
	function provideHttpMethodAndAPI() {
		$data = [];
		$methods = [
			"HEAD",
			"GET",
			"POST",
			"PUT",
			"DELETE",
			"PATCH"
		];
		foreach ($this->provideAPI() as $name => list($api)) {
			foreach ($methods as $method) {
				$data["$method $name"] = [$api, $method];
			}
		}
		return $data;
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testReturnsApiOnPropertyAccess($api) {
		$this->assertInstanceOf(API::class, $api->foo);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testReturnsCloneOnPropertyAccess($api) {
		$this->assertNotEquals($api, $api->bar);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testReturnsPromiseOnMethodCall($api) {
		$this->assertInstanceOf(Promise::class, $api->baz());
	}

	/**
	 * @dataProvider provideHttpMethodAndAPI
	 */
	function testProvidesMethodForStandardHttpMethod($api, $method) {
		$this->assertTrue(method_exists($api, $method));
	}

	/**
	 * @dataProvider provideHttpMethodAndAPI
	 */
	function testReturnsPromiseOnMethodCallForStandardHttpMethod($api, $method) {
		$this->assertInstanceOf(Promise::class, $api->$method());
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 * @depends testProvidesMethodForStandardHttpMethod
	 * @depends testReturnsPromiseOnMethodCallForStandardHttpMethod
	 */
	function testProvidesMethodsForStandardHttpMethods($api) {
		$this->assertTrue(true);
	}
}
