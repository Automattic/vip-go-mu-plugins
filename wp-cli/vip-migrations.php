<?php

class VIP_Go_Migrations_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Run dbDelta() for the current site.
	 *
	 * ## OPTIONS
	 *
	 * [<tables>]
	 * : Which tables to update (all, blog, global, ms_global)
	 */
	function dbdelta( $args ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( in_array( $args[1], array( '', 'all', 'blog', 'global', 'ms_global' ), true ) ) {
			$changes = dbDelta( $args[1] );
		} else {
			$changes = dbDelta();
		}

		if ( empty( $changes ) ) {
			WP_CLI::success( 'No changes.' );
			return;
		}

		foreach( $changes as $change ) {
			WP_CLI::line( $change );
		}

		WP_CLI::success( count( $changes ) . ' changes.' );
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
