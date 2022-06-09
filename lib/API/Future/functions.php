<?php

namespace seekat\API\Future;

use seekat\API\Future;

function react() : React2 {
	return new React2;
}

function amp() : Amp2 {
	return new Amp2;
}

function any() : Amp2|React2 {
	if (interface_exists(\Amp\Promise::class, true)) {
		return amp();
	}
	if (interface_exists(\React\Promise\PromiseInterface::class, true)) {
		return react();
	}
	throw new \Exception("Cannot find any promise implementation");
}
