<?php

use Automattic\VIP\Jetpack\Connection_Pilot;

use function Automattic\VIP\Migration\cleanup_local_imported_credentials;
use function Automattic\VIP\Migration\is_local_cleanup_environment;

class VIP_Data_Cleanup_Command extends WPCOM_VIP_CLI_Command {

	/**
	 * Run cleanup operations after a data sync.
	 *
	 * @subcommand datasync
	 */
	public function datasync() {
		global $wpdb;

		// Ensure all reads go to primary to prevent cache pollution.
		if ( property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = true;
		}

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
		if ( 'sqlimport' === $operation && is_local_cleanup_environment() ) {
			$this->cleanup_local_credentials();
		}

		$this->ensure_correct_global_schema();

		if ( ! is_multisite() ) {
			$this->cleanup_site( $operation );
		} else {
			global $wpdb;
			$iterator_args  = [
				'table'  => $wpdb->blogs,
				'where'  => [
					'spam'     => 0,
					'deleted'  => 0,
					'archived' => 0,
				],
				'fields' => [ 'blog_id' ],
			];
			$sites_iterator = new \WP_CLI\Iterators\Table( $iterator_args );

			foreach ( $sites_iterator as $site ) {
				switch_to_blog( $site->blog_id );

				$this->cleanup_site( $operation );

				restore_current_blog();
			}
		}
	}

	/**
	 * Sanitize every existing options table, including inactive subsites.
	 * Keep schema updates and customer hooks restricted to the usual active sites.
	 */
	private function cleanup_local_credentials(): void {
		wp_cache_flush();
		if ( ! is_multisite() ) {
			cleanup_local_imported_credentials();
			return;
		}

		global $wpdb;
		$sites = new \WP_CLI\Iterators\Table( [
			'table'  => $wpdb->blogs,
			'fields' => [ 'blog_id' ],
		] );
		foreach ( $sites as $site ) {
			$options_table = $wpdb->get_blog_prefix( $site->blog_id ) . 'options';
			// Deleted subsites may no longer have tables. Do not recreate them.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- CLI import cleanup.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $options_table ) ) ) !== $options_table ) {
				continue;
			}
			switch_to_blog( $site->blog_id );
			try {
				cleanup_local_imported_credentials();
			} finally {
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
			 * Runs on a child environment after receiving a data sync from production.
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

		// Flush cache again. After DB transient removal, and prevents the need for flushing on the individual hooks above.
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
