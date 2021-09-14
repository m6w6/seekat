<?php

class CacheTest extends BaseTest
{
	use ConsumePromise;
	use AssertSuccess;

	/**
	 * @var seekat\API\Call\Cache\Service
	 */
	private $cache;

	function setUp() : void {
		$this->cache = new seekat\API\Call\Cache\Service\Hollow;
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testCachesSuccessiveCalls($api) {
		$m6w6 = $this->assertSuccess($api->users->m6w6, null, null, $this->cache);
		$data = $this->cache->getStorage();
		$m6w6_ = $this->assertSuccess($api->users->m6w6, null, null, $this->cache);

		$this->assertEquals("m6w6", $m6w6->login);
		$this->assertEquals("m6w6", $m6w6_->login);

		$this->assertIsArray($data);
		$this->assertCount(1, $data);
		$this->assertEquals($data, $this->cache->getStorage());
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testRefreshesStaleCacheEntries($api) {
		$this->markTestIncomplete("TODO");
	}
}
