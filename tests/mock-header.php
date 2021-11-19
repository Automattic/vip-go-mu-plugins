<?php

namespace Automattic\Test;

$headers = [];

function header_remove( ?string $name = null ): void {
	global $headers;
	if ( null === $name ) {
		$headers = [];
	} else {
		foreach ( $headers as $index => $header ) {
			$parts = explode( ':', $header, 2 );
			$hname = strtolower( trim( $parts[0] ) );
			if ( ! strcasecmp( $hname, $name ) ) {
				unset( $headers[ $index ] );
			}
		}
	}
}

function headers_list(): array {
	global $headers;
	return array_values( $headers );
}

function headers_sent(): bool {
	global $headers;
	return ! empty( $headers );
}

function header( string $header, bool $replace = true ) {
	global $headers;

	if ( $replace ) {
		$parts = explode( ':', $header, 2 );
		$name  = trim( $parts[0] );
		namespace\header_remove( $name );
	}

	$headers[] = $header;
}
