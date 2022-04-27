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
		// Don't do anything for any command other than `db`
		return;
	}

	if ( ! is_array( $db_servers ) ) {
		echo "Error: No database servers are configured for this environment.\n";
		exit( 10 );
	}

	$allow_writes = defined( 'WPVIP_WP_DB_ALLOW_WRITES' ) && WPVIP_WP_DB_ALLOW_WRITES;

	$write_specific_subcommands = [
		'clean',
		'create',
		'drop',
		'import',
		'optimize',
		'repair',
		'reset',
	];

	$subcommand = $command[1];
	if ( ! $allow_writes && in_array( $subcommand, $write_specific_subcommands ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo "Error: The 'wp db $subcommand' subcommand is not currently allowed for this site.\n.";
		exit( 20 );
	}

	$_db_servers = array_filter( $db_servers, function ( $candidate ) use ( $allow_writes ) {
		if ( ! ( is_array( $candidate ) && 6 === count( $candidate ) ) ) {
			// This value isn't correctly formed. Remove.
			return false;
		}

		if ( $candidate[4] < 1 ) {
			// This server cannot read. Remove.
			return false;
		}

		if ( ! $allow_writes && $candidate[5] > 0 ) {
			// This server can write and it's not allowed. Remove.
			return false;
		}

		// Include the candidate in the list to sort.
		return true;
	} );

	if ( empty( $_db_servers ) ) {
		echo "Error: No database servers are available to fulfill this request.\n";
		exit( 30 );
	}

	// Sort the replicas in ascending order of the write priority (if allowed), else sort by read priority.
	usort( $_db_servers, function ( $c0, $c1 ) use ( $allow_writes ) {
		if ( $allow_writes ) {
			return $c0[5] <=> $c1[5];
		}
		return $c0[4] <=> $c1[4];
	} );

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

	// TODO: Logging
} );
