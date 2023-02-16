<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

use Exception;
use WP_CLI;
use WP_Error;

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
	 * @return WP_Error|null
	 */
	public function validate_subcommand( array $command ): ?WP_Error {
		$subcommand = $command[1] ?? '';

		$allowed_subcommands = [
			'query',
			'prefix',
			'columns',
			'size',
		];

		if ( ! in_array( $subcommand, $allowed_subcommands, true ) ) {
			return new \WP_Error( 'db-cli-disallowed-subcmd', "The `wp db $subcommand` subcommand is not permitted for this site." );
		}

		if ( 'query' === $subcommand && 2 === count( $command ) ) {
			// Doing `wp db query` without a DB query is the equivalent of doing `wp db cli`
			return new \WP_Error( 'db-cli-missing-query', 'Please provide the database query as a part of the command.' );
		}

		$query      = $command[2];
		$validation = $this->validate_query( $query );
		if ( is_wp_error( $validation ) ) {
			WP_CLI::error( $validation->get_error_message() );
		}
		return null;
	}

	/**
	 * Ensure the query is allowed.
	 *
	 * @param string $query
	 * @return WP_Error|null
	 */
	public function validate_query( string $query ): ?WP_Error {
		$query = strtolower( $query );

		if ( false !== strpos( $query, 'drop' ) ) {
			return new \WP_Error( 'db-cli-disallowed-query', 'This query is disallowed.' );
		}
		return null;
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

		if ( $this->config->is_local() && $this->config->is_batch() ) {
			return;
		}

		try {
			$server = $this->config->get_database_server();
		} catch ( Exception $e ) {
			// This will throw an error if db commands are not enabled for this env
			WP_CLI::error( $e->getMessage() );
		}

		if ( ! $this->config->is_sandbox() ) {
			$validation = $this->validate_subcommand( $command );
			if ( is_wp_error( $validation ) ) {
				WP_CLI::Error( $validation->get_error_message() );
			}
		}

		$server->define_variables();
	}
}
