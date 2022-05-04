<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

use Exception;
use WP_CLI;
use Automattic\VIP\Environment;

class Wp_Cli_Db {
	public function __construct( Config $config ) {
		$this->config = $config;
	}

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
	
		$this->add_before_run_db_command();
	}
	
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
	
	public function add_before_run_db_command() {
		WP_CLI::add_hook( 'before_run_command', function ( $command ) {
			if ( ! ( isset( $command[0] ) && 'db' === $command[0] ) ) {
				// Don't do anything for any command other than `db`
				return;
			}
	
			if ( ! $this->config->enabled() ) {
				echo 'ERROR: The db command is not currently supported in this environment.';
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
				echo 'ERROR: That db subcommand is not currently permitted for this site.';
				exit( 2 );
			}
	
			$server = $this->get_database_server();
			$server->define_variables();
			// TODO: Logging
		} );
	}
}
