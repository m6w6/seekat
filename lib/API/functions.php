<?php

namespace seekat\API;

/**
 * @param string $type
 * @param string $value
 * @return array
 */
function auth(string $type, string $value) : array {
	return ["Authorization" => "$type $value"];
}
