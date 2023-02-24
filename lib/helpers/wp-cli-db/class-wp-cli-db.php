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
	 * Ensure the command is allowed for the current Config.
	 *
	 * @throws Exception if the command is not allowed.
	 */
	public function validate_subcommand( array $command ): void {
		$subcommand = $command[1] ?? '';

		if ( 'query' !== $subcommand ) {
			throw new Exception( 'ERROR: Only the `wp db query` subcommand is permitted for this site.' );
		}

		if ( 2 === count( $command ) ) {
			throw new Exception( 'ERROR: Please provide the database query as a part of the command.' );
		}
	}

	/**
	 * Customize handling of the `wp db` command.
	 * Added to the WP_CLI `before_run_command` hook.
	 *
	 * @throws Exception if an uncaught error occurs in any of the called functions.
	 */
	public function before_run_command( array $command ): void {
		if ( ! ( isset( $command[0] ) && 'db' === $command[0] ) ) {
			// Don't do anything for any command other than `db`
			return;
		}

		// This will throw an exception if db commands are not enabled for this env:
		$server = $this->config->get_database_server();

		if ( ! $this->config->is_sandbox() ) {
			// This will throw an exception if the db subcommand is not valid:
			$this->validate_subcommand( $command );
		}

		$server->define_variables();
	}
}
