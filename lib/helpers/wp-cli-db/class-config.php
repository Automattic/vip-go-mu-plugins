<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

use Automattic\VIP\Environment;
use Exception;

class Config {
	private ?bool $enabled      = null;
	private ?bool $allow_writes = null;
	private ?bool $is_sandbox   = null;
	private ?bool $is_local     = null;
	private ?bool $is_batch     = null;

	public function enabled(): bool {
		if ( null === $this->enabled ) {
			$this->enabled = defined( 'WPVIP_ENABLE_WP_DB' ) && 1 === constant( 'WPVIP_ENABLE_WP_DB' ) || $this->is_batch() || $this->is_sandbox();
		}

		return $this->enabled;
	}

	public function allow_writes(): bool {
		if ( null === $this->allow_writes ) {
			$this->allow_writes = defined( 'WPVIP_ENABLE_WP_DB_WRITES' ) && 1 === constant( 'WPVIP_ENABLE_WP_DB_WRITES' );
		}

		return $this->allow_writes;
	}

	public function is_sandbox(): bool {
		if ( null === $this->is_sandbox ) {
			$this->is_sandbox = class_exists( Environment::class ) && Environment::is_sandbox_container( gethostname(), getenv() );
		}

		return $this->is_sandbox;
	}

	public function is_local(): bool {
		if ( null === $this->is_local ) {
			$this->is_local = defined( 'VIP_GO_APP_ENVIRONMENT' ) && constant( 'VIP_GO_APP_ENVIRONMENT' ) === 'local' || defined( 'WP_ENVIRONMENT_TYPE' ) && constant( 'WP_ENVIRONMENT_TYPE' ) === 'local';
		}

		return $this->is_local;
	}

	public function is_batch(): bool {
		if ( null === $this->is_batch ) {
			$this->is_batch = class_exists( Environment::class ) && Environment::is_batch_container( gethostname(), getenv() );
		}

		return $this->is_batch;
	}

	public function set_allow_writes( bool $allow ): void {
		$this->allow_writes = $allow;
	}

	/**
	 * Get the database server from the environment.
	 */
	public function get_database_server(): DB_Server {
		global $db_servers;

		if ( ! $this->enabled() ) {
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
