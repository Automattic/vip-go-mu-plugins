<?php

namespace Automattic\VIP\Core\Constants;

/**
 * Define DB_HOST, DB_USER, DB_PASSWORD, and DB_NAME constants.
 *
 * WPVIP uses use HyperDB, so DB_* constants are not automatically defined.
 * As a best practice, these constants should not be used directly, but we define them here in
 * case any customer code does to avoid fatal errors in PHP 8+.
 *
 * @param object $hyperdb
 */
function define_db_constants( $hyperdb ): void {
	if ( defined( 'DB_NAME' ) || defined( 'DB_HOST' ) || defined( 'DB_PASSWORD' ) || defined( 'DB_USER' ) ) {
		return;
	}

	if ( ! method_exists( $hyperdb, 'get_hyper_servers' ) ) {
		return;
	}

	$db_servers = $hyperdb->get_hyper_servers( 'write' );
	if ( ! is_array( $db_servers ) ) {
		return;
	}

	if ( isset( $db_servers[1] ) ) {
		// The index is based on the "write priority", which defaults to 1.
		// See https://github.com/Automattic/HyperDB/blob/06dc2449535c35aaaf2f7e1e8a28fb40e59ff23e/db.php#L257-L267
		$db = $db_servers[1];
	} else {
		$keys = array_keys( $db_servers );
		ksort( $keys );
		$db = $db_servers[ $keys[0] ] ?? null;
	}

	if ( isset( $db[0] ) && is_array( $db[0] ) ) {
		define( 'DB_HOST', $db[0]['host'] );
		define( 'DB_USER', $db[0]['user'] );
		define( 'DB_PASSWORD', $db[0]['password'] );
		define( 'DB_NAME', $db[0]['name'] );
	}
}
