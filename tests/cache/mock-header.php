<?php

namespace Automattic\VIP\Cache;

function header_remove( ?string $name = null ): void {
	\Automattic\Test\header_remove( $name );
}

function headers_list(): array {
	return \Automattic\Test\headers_list();
}

function headers_sent(): bool {
	return \Automattic\Test\headers_sent();
}

function header( string $header, bool $replace = true ) {
	\Automattic\Test\header( $header, $replace );
}

/**
 * Capture the cookie API boundary without sending native headers.
 */
class Cookie_Recorder {
	public static $calls = [];
}

// Test application identity for the direct constant used by encrypted cookies.
const VIP_GO_APP_ID = 123;

/**
 * Record an encoded cookie call with its effective default parameters.
 */
function setcookie( $name, $value = '', $expiry = 0, $path = '', $domain = '' ): bool {
	Cookie_Recorder::$calls[] = [ false, $name, $value, $expiry, $path, $domain ];
	return true;
}

/**
 * Record a raw cookie call, preserving the unencoded value.
 */
function setrawcookie( $name, $value = '', $expiry = 0, $path = '', $domain = '' ): bool {
	Cookie_Recorder::$calls[] = [ true, $name, $value, $expiry, $path, $domain ];
	return true;
}
