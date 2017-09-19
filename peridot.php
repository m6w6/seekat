<?php

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Peridot\Cli\Environment;
use Peridot\Cli\Application;
use Peridot\Configuration;
use Peridot\Core\Suite;
use Peridot\Core\Test;
use Peridot\Plugin\Scenarios;
use seekat\API;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

return function(\Peridot\EventEmitterInterface $emitter) {
	Scenarios\Plugin::register($emitter);

	$emitter->on("peridot.start", function(Environment $env, Application $app) {
		$app->setCatchExceptions(false);
		$definition = $env->getDefinition();
		$definition->getArgument("path")
			->setDefault(implode(" ", glob("tests/*")));
	});

	$log = new class extends AbstractProcessingHandler {
		private $records = [];
		protected function write(array $record) {
			$this->records[] = $record["formatted"];
		}
		function clean() {
			$this->records = [];
		}
		function dump(OutputInterface $output) {
			if ($this->records) {
				$output->writeln(["\n", "Debug log:", "==========="]);
				$output->write($this->records);
				$this->clean();
			}
		}
	};
	$emitter->on("suite.start", function(Suite $suite) use(&$headers, $log) {
		$headers = [];
		if (($token = getenv("GITHUB_TOKEN"))) {
			$headers["Authorization"] = "token $token";
		} elseif (function_exists("posix_isatty") && defined("STDIN") && posix_isatty(STDIN)) {
			fprintf(STDOUT, "GITHUB_TOKEN is not set in the environment, enter Y to continue without: ");
			fflush(STDOUT);
			if (strncasecmp(fgets(STDIN), "Y", 1)) {
				exit;
			}
		} else {
			throw new Exception("GITHUB_TOKEN is not set in the environment");
		}
		$suite->getScope()->amp = new API(API\Future\amp(),
			$headers, null, null, new Logger("amp", [$log]));
		$suite->getScope()->react = new API(API\Future\react(),
			$headers, null, null, new Logger("react", [$log]));
	});

	$emitter->on("test.failed", function(Test $test, \Throwable $e) {

	});
	$emitter->on("test.passed", function() use($log) {
		$log->clean();
	});
	$emitter->on("peridot.end", function($exitCode, InputInterface $input, OutputInterface $output) use($log) {
		$log->dump($output);
	});
};
