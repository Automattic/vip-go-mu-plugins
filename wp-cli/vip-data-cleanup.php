<?php

use Automattic\VIP\Jetpack\Connection_Pilot;

class VIP_Data_Cleanup_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Run cleanup operations after a data sync.
	 *
	 * @subcommand datasync
	 */
	public function datasync() {
		$this->cleanup_all_sites( 'datasync' );
		WP_CLI::success( 'Datasync cleanup completed.' );
	}

	/**
	 * Run cleanup operations after a SQL import.
	 *
	 * @subcommand sql-import
	 */
	public function sql_import() {
		// TODO: Would be ideal if we could pinpoint if just a specific subsite's blog tables were imported.
		$this->cleanup_all_sites( 'sqlimport' );
		WP_CLI::success( 'SQL Import cleanup completed.' );
	}

	private function cleanup_all_sites( $operation ) {
		$this->ensure_correct_global_schema();

		if ( ! is_multisite() ) {
			$this->cleanup_site( $operation );
		} else {
			$sites = get_sites();
			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				$this->cleanup_site( $operation );

				restore_current_blog();
			}
		}
	}

	private function cleanup_site( $operation ) {
		$this->ensure_correct_site_schema();

		// Flush cache before customization hooks are run, else can easily run into cache/db discrepancies.
		wp_cache_flush();

		if ( 'datasync' === $operation ) {
			/**
			 * Runs on a child environment after recieving a data sync from production.
			 */
			do_action( 'vip_datasync_cleanup' );

			if ( has_action( 'vip_go_migration_cleanup' ) ) {
				// TODO: deprecate w/ notices in the future.
				do_action( 'vip_go_migration_cleanup' );
			}
		}

		if ( 'sqlimport' === $operation ) {
			/**
			 * Runs after a SQL import has occurred on a site.
			 */
			do_action( 'vip_sqlimport_cleanup' );
		}

		$this->delete_db_transients();

		// Flush cache again. After DB transient removal, and prevents the need for flushing on the individiual hooks above.
		wp_cache_flush();

		if ( ! defined( 'VIP_JETPACK_SKIP_LOAD' ) || ! VIP_JETPACK_SKIP_LOAD ) {
			$connection_pilot = Connection_Pilot::instance();
			$connection_pilot->run_connection_pilot();
		}
	}

	/**
	 * We don't use transients on VIP Go as there is a real object cache,
	 * so we can delete any transients that may have come along after a SQL import.
	 */
	private function delete_db_transients() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%'" );
	}

	private function ensure_correct_global_schema() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		// Users/usermeta on single sites, plus extra multisite tables if a MS.
		dbDelta( 'global' );
	}

	private function ensure_correct_site_schema() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		// Tables related to individual sites/blogs, such as posts and options.
		dbDelta( 'blog' );
	}
}

WP_CLI::add_command( 'vip data-cleanup', 'VIP_Data_Cleanup_Command' );
