<?php

class VIP_Go_Convert_utf8_utf8mb4 extends WPCOM_VIP_CLI_Command {
	/**
	 * Command arguments
	 */
	private $dry_run = true;

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
			if ( isset( $assoc_args['dry-run'] ) && is_bool( $assoc_args['dry-run'] ) ) {
				$this->dry_run = $assoc_args['dry-run'];
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
	}

	/**
	 * If a table only contains utf8 or utf8mb4 columns, convert it to utf8mb4.
	 *
	 * Copied from wp-admin/includes/upgrade.php
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
			return null;
		} else {
			return true;
			//return $wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
		}
	}
}

WP_CLI::add_command( 'vip-go-utf8mb4', 'VIP_Go_Convert_utf8_utf8mb4' );
