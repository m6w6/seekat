# seekat

Fluent Github API access with PHP-7 and [ext-http](https://github.com/m6w6/ext-http).

```php
<?php

use seekat\API;

(new API)->repos->m6w6->seekat->readme->as("html")->then(function($readme) {
	echo $readme;
}, function($error) {
	echo $error;
});

$api->send();
```

> ***Note:*** WIP


## Installing

### Composer

	composer require m6w6/seekat

## ChangeLog

A comprehensive list of changes can be obtained from the
[releases overview](./releases).

## License

seekat is licensed under the 2-Clause-BSD license, which can be found in
the accompanying [LICENSE](./LICENSE) file.

## Contributing

All forms of contribution are welcome! Please see the bundled
[CONTRIBUTING](./CONTRIBUTING.md) note for the general principles followed.

The list of past and current contributors is maintained in [THANKS](./THANKS).
