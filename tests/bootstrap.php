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
	function provideAPI() {
		$headers = headers();
		$logger = logger();

		return [
			"using ReactPHP" => [new \seekat\API(\seekat\API\Future\react(), $headers, logger: $logger)],
			"using AmPHP"   => [new \seekat\API(\seekat\API\Future\amp(), $headers, logger: $logger)],
		];
	}
}

trait ConsumePromise
{
	function consumePromise($p, &$errors, &$results) {
		if (method_exists($p, "done")) {
			$p->then(function($result) use(&$results) {
				if (isset($result)) {
					$results[] = $result;
				}
			}, function($error) use (&$errors) {
				if (isset($error)) {
					$errors[] = $error;
				}
			});
		} else {
			$p->onResolve(function($error, $result) use(&$errors, &$results) {
				if (isset($error)) {
					$errors[] = $error;
				}
				if (isset($result)) {
					$results[] = $result;
				}
			});
		}
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
	function assertCancelled($promise) {
		$this->consumePromise($promise, $errors, $results);

		$this->assertEmpty($results);
		$this->assertStringMatchesFormat("%SCancelled%S", \seekat\Exception\message($errors[0]));
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

class CombinedTestdoxPrinter extends PHPUnit\TextUI\DefaultResultPrinter
{
	function isTestClass(PHPUnit\Framework\TestSuite $suite) {
		$suiteName = $suite->getName();
		return false === strpos($suiteName, "::")
			&& substr($suiteName, -4) === "Test";
	}

	function startTestSuite(PHPUnit\Framework\TestSuite $suite) : void {
		if ($this->isTestClass($suite)) {
			$this->column = 0;
		}

		parent::startTestSuite($suite);
	}

	function endTestSuite(PHPUnit\Framework\TestSuite $suite) : void {
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

class TestdoxListener implements PHPUnit\Framework\TestListener
{
	private $groups;
	private $testdox;

	function __construct() {
		$this->testdox = new PHPUnit\Util\TestDox\TextResultPrinter("php://stdout", ["testdox"]);
		$this->groups = new ReflectionProperty("\PHPUnit\Util\TestDox\ResultPrinter", "groups");
		$this->groups->setAccessible(true);
	}

	function startTest(PHPUnit\Framework\Test $test) : void {
		/* always show test class, even if no testdox test */
		if ($test instanceof \PHPUnit\Framework\TestCase) {
			if ($test->getGroups() == ["default"]) {
				$this->groups->setValue($this->testdox, ["default"]);
			}
		}

		$this->testdox->startTest($test);
		$this->groups->setValue($this->testdox, ["testdox"]);
	}

	public function addError(\PHPUnit\Framework\Test $test, Throwable $t, float $time): void
	{
		$this->testdox->addError($test, $t, $time);
	}

	public function addWarning(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\Warning $e, float $time): void
	{
		$this->testdox->addWarning($test, $e, $time);
	}

	public function addFailure(\PHPUnit\Framework\Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void
	{
		$this->testdox->addFailure($test, $e, $time);
	}

	public function addIncompleteTest(\PHPUnit\Framework\Test $test, Throwable $t, float $time): void
	{
		$this->testdox->addIncompleteTest($test, $t, $time);
	}

	public function addRiskyTest(\PHPUnit\Framework\Test $test, Throwable $t, float $time): void
	{
		$this->testdox->addRiskyTest($test, $t, $time);
	}

	public function addSkippedTest(\PHPUnit\Framework\Test $test, Throwable $t, float $time): void
	{
		$this->testdox->addSkippedTest($test, $t, $time);
	}

	public function startTestSuite(\PHPUnit\Framework\TestSuite $suite): void
	{
		$this->testdox->startTestSuite($suite);
	}

	public function endTestSuite(\PHPUnit\Framework\TestSuite $suite): void
	{
		$this->testdox->endTestSuite($suite);
	}

	public function endTest(\PHPUnit\Framework\Test $test, float $time): void
	{
		$this->testdox->endTest($test, $time);
	}
}

class DebugLogListener implements \PHPUnit\Framework\TestListener
{
	use \PHPUnit\Framework\TestListenerDefaultImplementation;

	private $printLog = false;

	function endTest(PHPUnit\Framework\Test $test, float $time) : void {
		/* @var $handler \Monolog\Handler\FingersCrossedHandler */
		$handler = logger()->getHandlers()[0];
		if ($this->printLog) {
			$this->printLog = false;
			$handler->activate();
		} else {
			$handler->clear();
		}
	}

	function addError(PHPUnit\Framework\Test $test, \Throwable $e, float $time) : void {
		$this->printLog = true;
	}

	function addFailure(PHPUnit\Framework\Test $test, PHPUnit\Framework\AssertionFailedError $e, float $time) : void {
		$this->printLog = true;
	}

}
