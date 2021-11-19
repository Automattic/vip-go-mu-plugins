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

function setcookie() {
	// Do nothing
}

function setrawcookie() {
	// Do nothing
}
