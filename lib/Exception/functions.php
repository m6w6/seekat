<?php

namespace seekat\Exception;

/**
 * Canonical error message from a string or Exception
 * @param string|Exception $error
 * @return string
 */
function message(&$error) : string {
	if ($error instanceof \Throwable) {
		$message = $error->getMessage();
	} else {
		$message = $error;
		$error = new \Exception($error);
	}
	return $message;
}
