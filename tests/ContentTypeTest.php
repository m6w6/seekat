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
		$api = (new ContentType($api->getVersion(), "json"))->apply($api);
		$this->assertEquals(
			$this->getVersionedContentType("json")->value,
			$api->export()["headers"]["Accept"]);

		$api = (new ContentType($api->getVersion(), "+raw"))->apply($api);
		$this->assertEquals(
			$this->getVersionedContentType("+raw")->value,
			$api->export()["headers"]["Accept"]);

		$api = (new ContentType($api->getVersion(), ".html"))->apply($api);
		$this->assertEquals(
			$this->getVersionedContentType(".html")->value,
			$api->export()["headers"]["Accept"]);
	}

	/**
	 * @group testdox
	 * @dataProvider provideAPI
	 */
	function testIsAbleToApplyBasicContentTypeToApi($api) {
		$api = (new ContentType($api->getVersion(), "text/plain"))->apply($api);
		$this->assertEquals("text/plain", $api->export()["headers"]["Accept"]);
	}

	/**
	 * @group testdox
	 */
	function testAllowsToRegisterAndUnregisterContentTypeHandlers() {
		$this->assertFalse(ContentType::registered("foobar"));
		ContentType::register(new class implements ContentType\Handler {
			function types() : array {
				return ["foobar"];
			}
			function encode($data): Body {
				return new Body;
			}
			function decode(Body $body) {
				return (string) $body;
			}
		});
		$this->assertTrue(ContentType::registered("foobar"));
		ContentType::unregister("foobar");
		$this->assertFalse(ContentType::registered("foobar"));
	}

	/**
	 * @group testdox
	 */
	function testAcceptsContentTypeHeader() {
		$ct = new ContentType;
		$ct->setContentTypeHeader(new Header("Content-Type", "TitleCase"));
		$this->assertSame("TitleCase", $ct->getType());
		$ct->setContentTypeHeader(new Header("content-type", "lowercase"));
		$this->assertSame("lowercase", $ct->getType());
	}

	/**
	 * @group testdox
	 */
	function testDoesNotAcceptNonContentTypeHeader() {
		$this->expectException(\seekat\Exception\InvalidArgumentException::class);
		(new ContentType)->setContentTypeHeader(new Header("ContentType"));
	}

	/**
	 * @group testdox
	 */
	function testThrowsOnUnknownContentType() {
		$this->expectException(\seekat\Exception\UnexpectedValueException::class);
		$ct = new ContentType(3, "foo");
		$ct->decode((new Body)->append("foo"));
	}

	/**
	 * @group testdox
	 */
	function testHandlesJson() {
		$this->assertTrue(ContentType::registered("json"));
		$ct = new ContentType(3, "application/json");
		$result = $ct->decode((new Body())->append("[1,2,3]"));
		$this->assertEquals([1, 2, 3], $result);
		return $ct;
	}

	/**
	 * @group testdox
	 * @depends testHandlesJson
	 */
	function testThrowsOnInvalidJson(ContentType $ct) {
		$this->expectException(\seekat\Exception\UnexpectedValueException::class);
		$ct->decode((new Body)->append("yaml:\n - data"));
	}

	/**
	 * @group testdox
	 */
	function testHandlesBase64() {
		$this->assertTrue(ContentType::registered("base64"));
		$ct = new ContentType(3,"base64");
		$result = $ct->decode((new Body())->append(base64_encode("This is a test")));
		$this->assertEquals("This is a test", $result);
		return $ct;
	}

	/**
	 * @group testdox
	 * @depends testHandlesBase64
	 */
	function testThrowsOnInvalidBase64(ContentType $ct) {
		$this->expectException(\seekat\Exception\UnexpectedValueException::class);
		$ct->decode((new Body)->append("[1,2,3]"));
	}

	/**
	 * @group testdox
	 */
	function testHandlesOctetStream() {
		$this->assertTrue(ContentType::registered("application/octet-stream"));
		$ct = new ContentType(3,"application/octet-stream");
		$result = $ct->decode((new Body)->append("This is a test"));
		$this->assertIsResource($result);
		rewind($result);
		$this->assertEquals("This is a test", stream_get_contents($result));
	}

	/**
	 * @group testdox
	 */
	function testHandlesData() {
		$this->assertTrue(ContentType::registered("text/plain"));
		$ct = new ContentType(3,"text/plain");
		$result = $ct->decode((new Body)->append("This is a test"));
		$this->assertIsString($result);
		$this->assertEquals("This is a test", $result);
	}

	/**
	 * @param string $type
	 * @return Header Content-Type header
	 */
	private function getVersionedContentType($type) {
		switch ($type[0]) {
			case ".":
			case "+":
			case "":
				break;
			default:
				$type = ".$type";
		}
		return new Header("Content-Type",
			sprintf("application/vnd.github.v3%s", $type));
	}
}
