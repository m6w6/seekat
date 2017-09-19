<?php

use seekat\API\ContentType;
use http\Header;
use http\Message\Body;

class ContentTypeTest extends BaseTest
{
	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testIsAbleToApplyVersionedContentTypeToApi($api) {
		$api = ContentType::apply($api, "json");
		$this->assertEquals(
			$this->getVersionedContentType("json")->value,
			$api->export()["headers"]["Accept"]);

		$api = ContentType::apply($api, "+raw");
		$this->assertEquals(
			$this->getVersionedContentType("+raw")->value,
			$api->export()["headers"]["Accept"]);

		$api = ContentType::apply($api, ".html");
		$this->assertEquals(
			$this->getVersionedContentType(".html")->value,
			$api->export()["headers"]["Accept"]);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testIsAbleToApplyBasicContentTypeToApi($api) {
		$api = ContentType::apply($api, "text/plain");
		$this->assertEquals("text/plain", $api->export()["headers"]["Accept"]);
	}

	/**
	 * @group testdox
	 */
	function testUserCanOverrideApiVersion() {
		$this->assertEquals(3, ContentType::version(2));
		$this->assertEquals(2, ContentType::version(3));
	}

	/**
	 * @group testdox
	 */
	function testAllowsToRegisterAndUnregisterContentTypeHandlers() {
		$this->assertFalse(ContentType::registered("foobar"));
		ContentType::register("foobar", function() {});
		$this->assertTrue(ContentType::registered("foobar"));
		ContentType::unregister("foobar");
		$this->assertFalse(ContentType::registered("foobar"));
	}

	/**
	 * @group testdox
	 */
	function testAcceptsContentTypeHeader() {
		new ContentType(new Header("Content-Type"));
		new ContentType(new Header("content-type"));
	}

	/**
	 * @group testdox
	 * @expectedException \seekat\Exception\InvalidArgumentException
	 */
	function testDoesNotAcceptNonContentTypeHeader() {
		new ContentType(new Header("ContentType"));
	}

	/**
	 * @group testdox
	 * @expectedException \seekat\Exception\UnexpectedValueException
	 */
	function testThrowsOnUnknownContentType() {
		$ct = new ContentType($this->getVersionedContentType("foo"));
		$ct->parseBody((new Body)->append("foo"));
	}

	/**
	 * @group testdox
	 */
	function testHandlesJson() {
		$this->assertTrue(ContentType::registered("json"));
		$ct = new ContentType(new Header("Content-Type", "application/json"));
		$result = $ct->parseBody((new Body())->append("[1,2,3]"));
		$this->assertEquals([1, 2, 3], $result);
		return $ct;
	}

	/**
	 * @group testdox
	 * @depends testHandlesJson
	 * @expectedException \seekat\Exception\UnexpectedValueException
	 */
	function testThrowsOnInvalidJson(ContentType $ct) {
		$ct->parseBody((new Body)->append("yaml:\n - data"));
	}

	/**
	 * @group testdox
	 */
	function testHandlesBase64() {
		$this->assertTrue(ContentType::registered("base64"));
		$ct = new ContentType($this->getVersionedContentType("base64"));
		$result = $ct->parseBody((new Body())->append(base64_encode("This is a test")));
		$this->assertEquals("This is a test", $result);
		return $ct;
	}

	/**
	 * @group testdox
	 * @depends testHandlesBase64
	 * @expectedException \seekat\Exception\UnexpectedValueException
	 */
	function testThrowsOnInvalidBase64(ContentType $ct) {
		$ct->parseBody((new Body)->append("[1,2,3]"));
	}

	/**
	 * @group testdox
	 */
	function testHandlesOctetStream() {
		$this->assertTrue(ContentType::registered("application/octet-stream"));
		$ct = new ContentType(new Header("Content-Type", "application/octet-stream"));
		$result = $ct->parseBody((new Body)->append("This is a test"));
		$this->assertInternalType("resource", $result);
		rewind($result);
		$this->assertEquals("This is a test", stream_get_contents($result));
	}

	/**
	 * @group testdox
	 */
	function testHandlesData() {
		$this->assertTrue(ContentType::registered("text/plain"));
		$ct = new ContentType(new Header("Content-Type", "text/plain"));
		$result = $ct->parseBody((new Body)->append("This is a test"));
		$this->assertInternalType("string", $result);
		$this->assertEquals("This is a test", $result);
	}

	/**
	 * @param string $type
	 * @return Header Content-Type header
	 */
	private function getVersionedContentType($type) {
		switch ($type{0}) {
			case ".":
			case "+":
			case "":
				break;
			default:
				$type = ".$type";
		}
		return new Header("Content-Type",
			sprintf("application/vnd.github.v%d%s", ContentType::version(), $type));
	}
}
