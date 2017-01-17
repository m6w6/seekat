<?php

namespace seekat;

/**
 * Generate a human readable represenation of a variable
 * @param mixed $arg
 * @param bool $export whether to var_export the $arg
 * @return string
 */
function typeof($arg, $export = false) {
	$type = (is_object($arg)
		? "instance of ".get_class($arg)
		: gettype($arg)
	);
	if ($export) {
		$type .= ": ".var_export($arg, true);
	}
	return $type;
}

