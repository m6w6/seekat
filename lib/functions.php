<?php

namespace {
	if (!function_exists("uri_template")) {
		function uri_template(string $str, array $arr = []) : string {
			$tpl = new \Rize\UriTemplate;
			return $tpl->expand($str, $arr);
		}
	}
}

namespace seekat {
	/**
	 * Generate a human readable representation of a variable
	 * @param mixed $arg
	 * @param bool $export whether to var_export the $arg
	 * @return string
	 */
	function typeof($arg, $export = false) {
		$type = is_object($arg)
			? "instance of ".get_class($arg)
			: gettype($arg);
		if ($export) {
			$type .= ": ".var_export($arg, true);
		}
		return $type;
	}
}
