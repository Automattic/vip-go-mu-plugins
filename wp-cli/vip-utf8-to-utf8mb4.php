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
}

WP_CLI::add_command( 'vip-go-utf8mb4', 'VIP_Go_Convert_utf8_utf8mb4' );
