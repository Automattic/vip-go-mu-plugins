<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

use Exception;
use WP_CLI;
use Automattic\VIP\Environment;

class Wp_Cli_Db {
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

		$hostname = gethostname();
		$env      = getenv();

		if ( ! (
			Environment::is_sandbox_container( $hostname, $env ) ||
			Environment::is_batch_container( $hostname, $env )
		) ) {
			return;
		}

		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_hook( 'before_run_command', [ $this, 'before_run_command' ] );
	}

	/**
	 * Get the database server from the environment.
	 */
	public function get_database_server(): DB_Server {
		global $db_servers;

		if ( ! is_array( $db_servers ) ) {
			throw new Exception( 'The database configuration is missing.' );
		}

		if ( empty( $db_servers ) ) {
			throw new Exception( 'The database configuration is empty.' );
		}

		$server_objects = array_map(
			function ( $server_tuple ) {
				if ( ! is_array( $server_tuple ) ) {
					return false;
				}
				return new DB_Server( ...$server_tuple );
			},
			$db_servers
		);

		$server_objects = array_filter( $server_objects, function ( $candidate ) {
			return $candidate instanceof DB_Server &&
				$candidate->can_read() && ! (
					$candidate->can_write() && ! $this->config->allow_writes()
				);
		} );

		// Sort the replicas in ascending order of the write priority (if allowed), else sort by read priority.
		usort( $server_objects, function ( $c0, $c1 ) {
			if ( $this->config->allow_writes() ) {
				return $c0->write_priority() <=> $c1->write_priority();
			}
			return $c0->read_priority() <=> $c1->read_priority();
		} );

		return end( $server_objects );
	}

	/**
	 * Customize handling of the `wp db` command.
	 * Added to the WP_CLI `before_run_command` hook.
	 */
	public function before_run_command( $command ) {
		if ( ! ( isset( $command[0] ) && 'db' === $command[0] ) ) {
			// Don't do anything for any command other than `db`
			return;
		}

		if ( ! $this->config->enabled() ) {
			echo "ERROR: The db command is not currently supported in this environment.\n";
			exit( 1 );
		}

		$write_specific_subcommands = [
			'clean',
			'create',
			'drop',
			'import',
			'optimize',
			'repair',
			'reset',
		];

		$subcommand = $command[1];

		if ( ! $this->config->allow_writes() && in_array( $subcommand, $write_specific_subcommands ) ) {
			echo "ERROR: That db subcommand is not currently permitted for this site.\n";
			exit( 2 );
		}

		if ( 'cli' === $subcommand || ( 'query' === $subcommand && 2 === count( $command ) ) ) {
			echo "ERROR: Direct access to the db console is not permitted at this time.\n";
			exit( 3 );
		}

		$server = $this->get_database_server();
		$server->define_variables();
	}
}
