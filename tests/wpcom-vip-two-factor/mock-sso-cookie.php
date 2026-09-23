<?php

namespace Automattic\VIP\TwoFactor;

// Capture only cookies issued by this module in the PHPUnit process.
function setcookie( $name, $value, $expires, $path, $domain, $secure, $httponly ) {
	$GLOBALS['vip_test_sso_cookies'][] = [
		'name'     => $name,
		'value'    => $value,
		'expires'  => $expires,
		'path'     => $path,
		'domain'   => $domain,
		'secure'   => $secure,
		'httponly' => $httponly,
	];
	return true;
}
