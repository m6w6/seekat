<?php

namespace seekat\Exception;

/**
 * @param string|\Throwable $message
 * @return \Throwable
 */
function exception(&$message) : \Throwable {
	if ($message instanceof \Throwable){
		$exception = $message;
		$message = $exception->getMessage();
	} else {
		$exception = new \Exception($message);
	}
	return $exception;
}

/**
 * Canonical error message from a string or Exception
 * @param string|\Throwable $error
 * @return string
 */
function message(&$error) : ?string {
	if ($error instanceof \Throwable) {
		$message = $error->getMessage();
	} else {
		$message = $error;
		$error = new \Exception($message);
	}
	return $message;
}
