<?php

class CacheTest extends BaseTest
{
	use ConsumePromise;
	use AssertSuccess;

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testCachesSuccessiveCalls($api) {
		$api->getCache()->clear();
		$m6w6 = $this->assertSuccess($api->users->m6w6);
		$data = $api->getCache()->getStorage();
		$m6w6_ = $this->assertSuccess($api->users->m6w6);

		$this->assertEquals("m6w6", $m6w6->login);
		$this->assertEquals("m6w6", $m6w6_->login);

		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertEquals($data, $api->getCache()->getStorage());
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testRefreshesStaleCacheEntries($api) {
		$api->getCache()->clear();
		$m6w6 = $this->assertSuccess($api->users->m6w6);

		$data = $api->getCache()->getStorage();
		/* @var \http\Header $resp */
		$resp = current($data);
		$resp->setHeader("X-Cache-Time", null);
		$resp->setHeader("Cache-Control", null);
		$resp->setHeader("Expires", 0);

		$m6w6_ = $this->assertSuccess($api->users->m6w6);

		$this->assertEquals("m6w6", $m6w6->login);
		$this->assertEquals("m6w6", $m6w6_->login);

		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertEquals($data, $api->getCache()->getStorage());

		$this->assertIsNumeric($resp->getHeader("X-Cache-Time"));
	}
}
