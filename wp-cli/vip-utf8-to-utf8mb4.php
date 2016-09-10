<?php

class VIP_Go_Convert_utf8_utf8mb4 extends WPCOM_VIP_CLI_Command {
	/**
	 * Command arguments
	 */
	private $dry_run = true;

	/**
	 * Class properties
	 */
	private $tables = array();

	/**
	 * Convert site using `utf8` to use `utf8mb4`
	 *
	 * @subcommand convert
	 */
	public function convert( $args, $assoc_args ) {
		global $wpdb;

		WP_CLI::line( 'CONVERSION TO `utf8mb4` REQUESTED' );

		// Parse arguments
		if ( is_array( $assoc_args ) && ! empty( $assoc_args ) ) {
			if ( isset( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {
				$this->dry_run = false;
			}
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'ARGUMENTS' );
		WP_CLI::line( '* dry run: ' . ( $this->dry_run ? 'yes' : 'no' ) );
		WP_CLI::line( '' );

		// Validate starting charset to avoid catastrophe
		WP_CLI::line( 'PREFLIGHT CHECKS' );
		if ( 'utf8' === $wpdb->charset ) {
			WP_CLI::line( '* Expected charset (`utf8`) found.' );
		} elseif ( 'utf8mb4' === $wpdb->charset ) {
			WP_CLI::error( 'Site is already using `utf8mb4`. Aborting!' );
			return;
		} else {
			WP_CLI::error( "Unacceptable starting encoding: `{$wpdb->charset}`. Aborting!" );
			return;
		}

		// Describe scope
		if ( is_multisite() ) {
			WP_CLI::line( '* Multisite detected, so this process will convert all network and global tables, along with the blog tables for all sites.' );
		} else {
			WP_CLI::line( '* Single site detected, so global and blog-specific tables will be converted. Any multisite tables will be skipped.' );
		}

		// Describe tables to be converted
		$this->get_tables();
		$tables_count = number_format( count( $this->tables ) );
		$tables_string = implode( ', ', $this->tables );

		WP_CLI::line( "* Found {$tables_count} tables to check and potentially convert: {$tables_string}." );
		WP_CLI::line( '' );

		// Provide an opportunity to abort
		WP_CLI::confirm( "Proceed and potentially convert {$tables_count} tables from `utf8` to `utf8mb4`?" );
		WP_CLI::line( '' );
		WP_CLI::line( 'Proceeding...' );
		WP_CLI::line( '' );

		unset( $tables_count, $tables_string );

		// Do the work we came here for
		foreach ( $this->tables as $table ) {
			WP_CLI::line( "Converting {$table}..." );

			$converted = $this->maybe_convert_table_to_utf8mb4( $table );

			if ( true === $converted ) {
				WP_CLI::line( "Done with {$table}." );
			} elseif ( false === $converted ) {
				if ( $this->dry_run ) {
					WP_CLI::line( "Table {$table} not converted during dry run." );
				} else {
					WP_CLI::line( "Table {$table} not converted because it doesn't exist or doesn't contain convertible columns." );
				}
			} else {
				WP_CLI::line( 'Unknown response: ' . var_export( $converted, true ) );
			}

			WP_CLI::line( '' );
		}

		// Wrap up
		WP_CLI::line( '' );
		WP_CLI::line( '' );
		WP_CLI::line( 'DONE!' );
		WP_CLI::line( 'Time to update sitemeta and reload web configs.' );
	}

	/**
	 * UTILITY METHODS
	 */

	/**
	 * Populate array of tables to possibly convert
	 *
	 * NOTE: We don't use `$wpdb->tables( 'all' )` because it won't include all tables
	 * for every site in a multisite network. Instead, it will only include the current
	 * site's tables, along with the global tables. We want ALL tables, so we query for
	 * sites and merge each site's tables into a single array.
	 */
	private function get_tables() {
		global $wpdb;

		// Start with the global tables
		// Under multisite, this includes the global multisite tables
		$tables = array_values( $wpdb->tables( 'global' ) );

		// Add blog-specific tables
		if ( is_multisite() ) {
			$site_ids = get_sites( array(
				'fields' => 'ids',
			) );

			if ( is_array( $site_ids ) && ! empty( $site_ids ) ) {
				foreach ( $site_ids as $site_id ) {
					$tables = array_merge( $tables, array_values( $wpdb->tables( 'blog', true, $site_id ) ) );
				}

				unset( $site_id );
			}

			unset( $site_ids );
		} else {
			$tables = array_merge( $tables, array_values( $wpdb->tables( 'blog' ) ) );
		}

		// Store and return
		$this->tables = array_unique( $tables );
		unset( $tables );

		return $this->tables;
	}

	/**
	 * If a table only contains utf8 or utf8mb4 columns, convert it to utf8mb4.
	 *
	 * Copied from wp-admin/includes/upgrade.php, with modifications for CLI usage
	 */
	private function maybe_convert_table_to_utf8mb4( $table ) {
		global $wpdb;

		$results = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$table`" );
		if ( ! $results ) {
			return false;
		}

		foreach ( $results as $column ) {
			if ( $column->Collation ) {
				list( $charset ) = explode( '_', $column->Collation );
				$charset = strtolower( $charset );
				if ( 'utf8' !== $charset && 'utf8mb4' !== $charset ) {
					// Don't upgrade tables that have non-utf8 columns.
					return false;
				}
			}
		}

		$table_details = $wpdb->get_row( "SHOW TABLE STATUS LIKE '$table'" );
		if ( ! $table_details ) {
			return false;
		}

		list( $table_charset ) = explode( '_', $table_details->Collation );
		$table_charset = strtolower( $table_charset );
		if ( 'utf8mb4' === $table_charset ) {
			return true;
		}

		if ( $this->dry_run ) {
			return false;
		} else {
			return true;
			//return $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
		}
	}
}

WP_CLI::add_command( 'vip-go-utf8mb4', 'VIP_Go_Convert_utf8_utf8mb4' );
