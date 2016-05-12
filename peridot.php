<?php

use Evenement\EventEmitterInterface as EventEmitter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Peridot\Configuration;
use Peridot\Console\Application;
use Peridot\Console\Environment;
use Peridot\Core\Suite;
use Peridot\Core\Test;
use seekat\API;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Peridot\Reporter\CodeCoverage\AbstractCodeCoverageReporter;
use Peridot\Reporter\CodeCoverageReporters;
use Peridot\Reporter\ReporterFactory;
use Peridot\Reporter\AnonymousReporter;
use Peridot\Reporter\AbstractBaseReporter;

return function(EventEmitter $emitter) {
	(new CodeCoverageReporters($emitter))->register();

	$emitter->on('peridot.reporters', function(InputInterface $input, ReporterFactory $reporterFactory) {
        $reporterFactory->register(
            'seekat',
            'Spec + Text Code coverage reporter',
            function(AnonymousReporter $ar) use ($reporterFactory) {

				return new class($reporterFactory, $ar->getConfiguration(), $ar->getOutput(), $ar->getEventEmitter()) extends AbstractBaseReporter {
					private $reporters = [];

					function __construct(ReporterFactory $factory, Configuration $configuration, OutputInterface $output, EventEmitter $eventEmitter) {
						fprintf(STDERR, "Creating reporters\n");
						$this->reporters[] = $factory->create("spec");
						$this->reporters[] = $factory->create("text-code-coverage");
						parent::__construct($configuration, $output, $eventEmitter);
					}

					function init() {
					}
					function __2call($method, array $args) {
						fprintf(STDERR, "Calling %s\n", $method);
						foreach ($this->reporters as $reporter) {
							$output = $reporter->$method(...$args);
						}
						return $output;
					}
				};
			}
        );
	});

	$emitter->on('code-coverage.start', function (AbstractCodeCoverageReporter $reporter) {
        $reporter->addDirectoryToWhitelist(__DIR__."/lib")
			->addDirectoryToWhitelist(__DIR__."/tests");
	});

	$emitter->on("peridot.start", function(Environment $env, Application $app) {
		$app->setCatchExceptions(false);
		$definition = $env->getDefinition();
		$definition->getArgument("path")
			->setDefault(implode(" ", glob("tests/*")));
		$definition->getOption("reporter")
			->setDefault("seekat");
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
	$emitter->on("suite.start", function(Suite $suite) use($log) {
		$headers = [];
		if (($token = getenv("GITHUB_TOKEN"))) {
			$headers["Authentication"] = "token $token";
		} elseif (function_exists("posix_isatty") && defined("STDIN") && posix_isatty(STDIN)) {
			fprintf(STDOUT, "GITHUB_TOKEN is not set in the environment, enter Y to continue without: ");
			fflush(STDOUT);
			if (strncasecmp(fgets(STDIN), "Y", 1)) {
				exit;
			}
		} else {
			throw new Exception("GITHUB_TOKEN is not set in the environment");
		}
		$suite->getScope()->api = new API($headers, null, null, new Logger("seekat", [$log]));
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
