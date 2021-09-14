<?php

class ErrorsTest extends BaseTest
{
	use ConsumePromise;
	use AssertCancelled;
	use AssertFailure;

	/**
	 * @dataProvider provideAPI
	 */
	function testCancellation($api) {
		$promise = $api->users->m6w6();

		$api->getFuture()->cancelPromise($promise);
		$this->assertCancelled($promise);
	}

	/**
	 * @dataProvider provideAPI
	 */
	function test404($api) {
		$error = $this->assertFailure($api->generate->a404);
		$this->assertEquals($error->getMessage(), "Not Found");
	}
}
