<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

use Exception;

class Config {
	private bool $enabled      = false;
	private bool $allow_writes = false;

	public function __construct() {
		$this->enabled      = defined( 'WPVIP_ENABLE_WP_DB' ) && 1 === constant( 'WPVIP_ENABLE_WP_DB' );
		$this->allow_writes = defined( 'WPVIP_ENABLE_WP_DB_WRITES' ) && 1 === constant( 'WPVIP_ENABLE_WP_DB_WRITES' );
	}

	public function enabled(): bool {
		return $this->enabled;
	}

	public function allow_writes(): bool {
		return $this->allow_writes;
	}

	/**
	 * Get the database server from the environment.
	 */
	public function get_database_server(): DB_Server {
		global $db_servers;

		if ( ! $this->enabled ) {
			throw new Exception( 'The db command is not currently supported in this environment.' );
		}

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
					$candidate->can_write() && ! $this->allow_writes()
				);
		} );

		// Sort the replicas in ascending order of the write priority (if allowed), else sort by read priority.
		usort( $server_objects, function ( $c0, $c1 ) {
			if ( $this->allow_writes() ) {
				return $c0->write_priority() <=> $c1->write_priority();
			}
			return $c0->read_priority() <=> $c1->read_priority();
		} );

		return end( $server_objects );
	}
}
