<?php

/**
 * This file is sourced in wp-config.php so we can limit the critical path to the specific `wp db` command.
 */

if ( defined( 'WPINC' ) ) {
	// Don't do anything when WordPress is loaded.
	return;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

WP_CLI::add_hook( 'before_run_command', function ( $command ) {
	global $db_servers;

	if ( ! ( isset( $command[0] ) && 'db' === $command[0] ) ) {
		return;
	}

	if ( ! is_array( $db_servers ) ) {
		return;
	}

	// Remove any servers that can't both read and write.
	$_db_servers = array_filter( $db_servers, function ( $candidate ) {
		return is_array( $candidate ) &&
			6 === count( $candidate ) &&
			$candidate[4] > 0 &&
			$candidate[5] > 0;
	} );

	if ( empty( $_db_servers ) ) {
		return;
	}

	// Sort the replicas in ascending order of WritePriority
	usort( $_db_servers, function ( $c0, $c1 ) {
		return $c0[5] <=> $c1[5];
	} );

	// Select the server with the higest write priority
	$server = end( $_db_servers );

	if ( ! defined( 'DB_HOST' ) ) {
		define( 'DB_HOST', $server[0] );
	}

	if ( ! defined( 'DB_USER' ) ) {
		define( 'DB_USER', $server[1] );
	}
	if ( ! defined( 'DB_PASSWORD' ) ) {
		define( 'DB_PASSWORD', $server[2] );
	}
	if ( ! defined( 'DB_NAME' ) ) {
		define( 'DB_NAME', $server[3] );
	}
} );
