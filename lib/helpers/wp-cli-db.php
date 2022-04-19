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

	if ( ! is_array( $db_servers ) || empty( $db_servers ) ) {
		return;
	}

	$server = end( $db_servers );

	if ( empty( $server ) ) {
		return;
	}

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
