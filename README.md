# seekat

[![Build Status](https://travis-ci.org/m6w6/seekat.svg)](https://travis-ci.org/m6w6/seekat)

Fluent Github API access with [ext-http](https://github.com/m6w6/ext-http).

Support for the following promise providers built in:
 * [ReactPHP](https://github.com/reactphp/promise)
 * [AmPHP](https://github.com/amphp/amp)

Supports plugging into your favourite event loop through
[http\Client's custom event loop interface](https://mdref.m6w6.name/http/Client/Curl/User).

Simple example:

```php
<?php

use seekat\API;

$api = new API(API\Future\react());

$api->repos->m6w6->seekat->readme->as("html")->then(function($readme) {
	echo $readme;
}, function($error) {
	echo $error;
});

$api->send();
```

Full example:

```php
<?php

require_once __DIR__."/../vendor/autoload.php";

use seekat\API;
use function seekat\API\Links\next;

$cli = new http\Client("curl", "seekat");
$cli->configure([
	"max_host_connections" => 10,
	"max_total_connections" => 50,
]);

$log = new Monolog\Logger("seekat");
$log->pushHandler(new Monolog\Handler\StreamHandler(STDERR, Monolog\Logger::WARNING));

$api = new API(API\Future\react(), [
	"Authorization" => "token ".getenv("GITHUB_TOKEN")
], null, $cli, $log);

$api(function($api) {
	$repos = yield $api->users->m6w6->repos([
		"visibility" => "public",
		"affiliation" => "owner"
	]);
	while ($repos) {
		$next = next($repos);

		$batch = [];
		foreach ($repos as $repo) {
			$batch[] = $repo->hooks();
		}
		foreach (yield $batch as $key => $hooks) {
			if (!count($hooks)) {
				continue;
			}
			printf("%s:\n", $repos->{$key}->name);
			foreach ($hooks as $hook) {
				if ($hook->name == "web") {
					printf("\t%s\n", $hook->config->url);
				} else {
					printf("\t%s\n", $hook->name);
				}
			}
		}

		$repos = yield $next;
	}
});
```


## Installing

### Composer

	composer require m6w6/seekat

## ChangeLog

A comprehensive list of changes can be obtained from the
[releases overview](https://github.com/m6w6/seekat/releases).

## License

seekat is licensed under the 2-Clause-BSD license, which can be found in
the accompanying [LICENSE](./LICENSE) file.

## Contributing

All forms of contribution are welcome! Please see the bundled
[CONTRIBUTING](./CONTRIBUTING.md) note for the general principles followed.

The list of past and current contributors is maintained in [THANKS](./THANKS).
