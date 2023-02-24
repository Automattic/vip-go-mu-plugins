<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

use Exception;
use WP_CLI;

class Wp_Cli_Db {
	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * If conditions are correct, hook into WP-CLI before_run_command to use the correct configuration.
	 * This is intended to be called before WordPress is loaded (e.g. in wp-config.php).
	 */
	public function early_init() {
		if ( defined( 'WPINC' ) ) {
			// Don't do anything when WordPress is loaded.
			return;
		}

		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_hook( 'before_run_command', [ $this, 'before_run_command' ] );
	}

	/**
	 * Ensure the command or query is allowed for the current Config.
	 *
	 * @param array $command
	 * @return Exception|void
	 */
	public function validate_subcommand( array $command ) {
		$subcommand = $command[1] ?? '';

		$allowed_subcommands = [
			'query',
			'prefix',
			'columns',
			'size',
		];

		if ( ! in_array( $subcommand, $allowed_subcommands, true ) ) {
			throw new Exception( "The `wp db $subcommand` subcommand is not permitted for this site." );
		}

		if ( 'query' === $subcommand ) {
			if ( 2 === count( $command ) ) {
				// Doing `wp db query` without a DB query is the equivalent of doing `wp db cli`
				throw new Exception( 'Please provide the database query as a part of the command.' );
			}

			$query      = $command[2];
			$validation = $this->validate_query( $query );
			if ( ! $validation ) {
				throw new Exception( 'This query is disallowed.' );
			}
		}
	}

	/**
	 * Ensure the query is allowed.
	 *
	 * @param string $query Query to execute.
	 * @return bool Whether query is allowed or not.
	 */
	public function validate_query( string $query ) {
		$query = strtolower( $query );

		$disallowed_syntax = [ 'drop', 'create', 'truncate' ];

		foreach ( $disallowed_syntax as $syntax ) {
			if ( false !== strpos( $query, $syntax ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Customize handling of the `wp db` command.
	 * Added to the WP_CLI `before_run_command` hook.
	 *
	 * @param array $command
	 */
	public function before_run_command( array $command ): void {
		if ( ! ( isset( $command[0] ) && 'db' === $command[0] ) ) {
			// Don't do anything for any command other than `db`
			return;
		}

		if ( $this->config->is_local() ) {
			return;
		}

		try {
			$server = $this->config->get_database_server();
		} catch ( Exception $e ) {
			// This will throw an error if db commands are not enabled for this env
			WP_CLI::error( $e->getMessage() );
		}

		try {
			$this->validate_subcommand( $command );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$server->define_variables();
	}
}
