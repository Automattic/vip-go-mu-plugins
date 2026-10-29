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

	/**
	 * Record an options-array call as [ raw, name, value, expires, path, domain, secure, httponly ],
	 * filling in the native defaults for omitted options.
	 */
	public static function record( bool $raw, $name, $value, array $options ): bool {
		$options += [
			'expires'  => 0,
			'path'     => '',
			'domain'   => '',
			'secure'   => false,
			'httponly' => false,
		];

		self::$calls[] = [ $raw, $name, $value, $options['expires'], $options['path'], $options['domain'], $options['secure'], $options['httponly'] ];
		return true;
	}
}

// Test application identity for the direct constant used by encrypted cookies.
const VIP_GO_APP_ID = 123;

/**
 * Record an encoded cookie call.
 */
function setcookie( $name, $value = '', array $options = [] ): bool {
	return Cookie_Recorder::record( false, $name, $value, $options );
}

/**
 * Record a raw cookie call, preserving the unencoded value.
 */
function setrawcookie( $name, $value = '', array $options = [] ): bool {
	return Cookie_Recorder::record( true, $name, $value, $options );
}
