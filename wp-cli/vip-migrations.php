<?php

use \WP_CLI\Utils;

class VIP_Go_Migrations_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Run dbDelta() for the current site.
	 *
	 * [--network]
	 * : Update databases for all sites on a network
	 *
	 * [--dry-run]
	 * : Show changes without updating
	 *
	 * ## OPTIONS
	 *
	 * [<tables>]
	 * : Which tables to update (all, blog, global, ms_global)
	 */
	function dbdelta( $args, $assoc_args ) {
		global $wpdb;

		$network = Utils\get_flag_value( $assoc_args, 'network' );
		if ( $network && ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run' );
		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		}

		if ( $network ) {
			$iterator_args = array(
				'table' => $wpdb->blogs,
				'where' => array( 'spam' => 0, 'deleted' => 0, 'archived' => 0 ),
			);
			$it = new \WP_CLI\Iterators\Table( $iterator_args );
			$success = $total = 0;
			$site_ids = array();
			foreach( $it as $blog ) {
				$total++;
				$site_ids[] = $blog->site_id;
				$url = $blog->domain . $blog->path;
				$cmd = "--url={$url} vip migration dbdelta blog";
				if ( $dry_run ) {
					$cmd .= ' --dry-run';
				}

				WP_CLI::line();
				WP_CLI::line( WP_CLI::colorize( '%mUpdating:%n ' ) . $blog->domain . $blog->path );
				WP_CLI::runcommand( $cmd );
			}
			return;
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( in_array( $args[1], array( '', 'all', 'blog', 'global', 'ms_global' ), true ) ) {
			$changes = dbDelta( $args[1], !$dry_run );
		} else {
			$changes = dbDelta( null, !$dry_run );
		}

		if ( empty( $changes ) ) {
			WP_CLI::success( 'No changes.' );
			return;
		}

		foreach( $changes as $change ) {
			WP_CLI::line( $change );
		}

		$count = count( $changes );
		WP_CLI::success( _n( '%s change', '%s changes', $count ), number_format_i18n( $count ) ) );
	}
}

WP_CLI::add_command( 'vip migration', 'VIP_Go_Migrations_Command' );
