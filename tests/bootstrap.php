<?php

require_once __DIR__."/../vendor/autoload.php";

function headers() : array {
	static $headers;

	if (!isset($headers)) {
		if (($token = getenv("GITHUB_TOKEN"))) {
			$headers["Authorization"] = "token $token";
		} elseif (function_exists("posix_isatty") && defined("STDIN") && posix_isatty(STDIN)) {
			fprintf(STDOUT, "GITHUB_TOKEN is not set in the environment, enter Y to continue without: ");
			fflush(STDOUT);
			if (strncasecmp(fgets(STDIN), "Y", 1)) {
				exit;
			}
			$headers = [];
		} else {
			throw new Exception("GITHUB_TOKEN is not set in the environment");
		}
	}

	return $headers;
}

function logger() : \Monolog\Logger {
	static $logger;

	if (!isset($logger)) {
		$logger = new \Monolog\Logger(
			"test",
			[
				new \Monolog\Handler\FingersCrossedHandler(
					new \Monolog\Handler\StreamHandler(STDERR),
					\Monolog\Logger::EMERGENCY
				)
			]
		);
	}

	return $logger;
}

class BaseTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @return Generator
	 */
	function provideAPI() {
		$auth = \seekat\API\auth("token", getenv("GITHUB_TOKEN"));
		$headers = headers();
		$url = null;
		$client = null;
		$logger = logger();

		return [
			"with ReactPHP" => [new \seekat\API(\seekat\API\Future\react(), $headers, $url, $client, $logger)],
			"with AmPHP"   => [new \seekat\API(\seekat\API\Future\amp(), $headers, $url, $client, $logger)],
		];
	}
}

trait ConsumePromise
{
	function consumePromise(\AsyncInterop\Promise $p, &$errors, &$results) {
		$p->when(function($error, $result) use(&$errors, &$results) {
			if ($error) $errors[] = $error;
			if ($result) $results[] = $result;
		});
	}
}

trait AssertSuccess
{
	function assertAllSuccess(array $apis, ...$args) {
		foreach ($apis as $api) {
			$this->consumePromise($api->get(...$args), $errors, $results);
		}
		$api->send();
		$this->assertEmpty($errors, "errors");
		$this->assertNotEmpty($results, "results");
		return $results;
	}

	function assertSuccess(seekat\API $api, ...$args) {
		$this->consumePromise($api->get(...$args), $errors, $results);
		$api->send();
		$this->assertEmpty($errors, "errors");
		$this->assertNotEmpty($results, "results");
		return $results[0];
	}
}

trait AssertCancelled
{
	function assertCancelled(\AsyncInterop\Promise $promise) {
		$this->consumePromise($promise, $errors, $results);

		$this->assertEmpty($results);
		$this->assertStringMatchesFormat("%SCancelled%S", $errors[0]->getMessage());
	}
}

trait AssertFailure
{
	function assertFailure(seekat\API $api, ...$args) {
		$this->consumePromise($api->get(...$args), $errors, $results);
		$api->send();
		$this->assertNotEmpty($errors, "errors");
		$this->assertEmpty($results, "results");
		return $errors[0];
	}
}

class CombinedTestdoxPrinter extends PHPUnit_TextUI_ResultPrinter
{
	function isTestClass(PHPUnit_Framework_TestSuite $suite) {
		$suiteName = $suite->getName();
		return false === strpos($suiteName, "::")
			&& substr($suiteName, -4) === "Test";
	}

	function startTestSuite(PHPUnit_Framework_TestSuite $suite) {
		if ($this->isTestClass($suite)) {
			$this->column = 0;
		}

		return parent::startTestSuite($suite);
	}

	function endTestSuite(PHPUnit_Framework_TestSuite $suite) {
		/* print % progress */
		if ($this->isTestClass($suite)) {
			if ($this->numTestsRun != $this->numTests) {
				$colWidth = $this->maxColumn - $this->column;
				$this->column = $this->maxColumn - 1;

				--$this->numTestsRun;
				$this->writeProgress(str_repeat(" ", $colWidth));
			} else {
				$this->writeNewLine();
			}
		}

		parent::endTestSuite($suite);
	}
}

class TestdoxListener extends PHPUnit_Util_TestDox_ResultPrinter_Text
{
	private $groups;

	function __construct() {
		parent::__construct("php://stdout", ["testdox"]);
		$this->groups = new ReflectionProperty("PHPUnit_Util_TestDox_ResultPrinter", "groups");
		$this->groups->setAccessible(true);
	}

	function startTest(PHPUnit_Framework_Test $test) {
		/* always show test class, even if no testdox test */
		if ($test instanceof \PHPUnit\Framework\TestCase) {
			if ($test->getGroups() == ["default"]) {
				$this->groups->setValue($this, ["default"]);
			}
		}

		parent::startTest($test);
		$this->groups->setValue($this, ["testdox"]);

	}
}

class DebugLogListener extends PHPUnit\Framework\BaseTestListener
{
	private $printLog = false;

	function endTest(PHPUnit_Framework_Test $test, $time) {
		/* @var $handler \Monolog\Handler\FingersCrossedHandler */
		$handler = logger()->getHandlers()[0];
		if ($this->printLog) {
			$this->printLog = false;
			$handler->activate();
		} else {
			$handler->clear();
		}
	}

	function addError(PHPUnit_Framework_Test $test, Exception $e, $time) {
		$this->printLog = true;
	}

	function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time) {
		$this->printLog = true;
	}

}
