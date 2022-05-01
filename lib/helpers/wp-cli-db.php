<?php
/**
 * This file is sourced in wp-config.php so we can limit the critical path to the specific `wp db` command.
 */

namespace Automattic\VIP\Helpers;

use Exception;
use WP_CLI;

class Config {
	private bool $allow_writes = false;

	public function __construct() {
		$this->allow_writes = defined( 'WPVIP_WP_DB_ALLOW_WRITES' ) && WPVIP_WP_DB_ALLOW_WRITES;
	}

	public function allow_writes(): bool {
		return $this->allow_writes;
	}
}

class DB_Server {
	private $host;
	private $user;
	private $password;
	private $name;
	private $read_priority;
	private $write_priority;

	public function __construct( string $host, string $user, string $password, string $name, int $read_priority, int $write_priority ) {
		$this->host           = $host;
		$this->user           = $user;
		$this->password       = $password;
		$this->name           = $name;
		$this->read_priority  = $read_priority;
		$this->write_priority = $write_priority;
	}

	public function define_variables() {
		if ( ! defined( 'DB_HOST' ) ) {
			define( 'DB_HOST', $this->host );
		}
		if ( ! defined( 'DB_USER' ) ) {
			define( 'DB_USER', $this->user );
		}
		if ( ! defined( 'DB_PASSWORD' ) ) {
			define( 'DB_PASSWORD', $this->password );
		}
		if ( ! defined( 'DB_NAME' ) ) {
			define( 'DB_NAME', $this->name );
		}
	}

	public function can_read() {
		return $this->read_priority > 0;
	}

	public function can_write() {
		return $this->write_priority > 0;
	}

	public function read_priority() {
		return $this->read_priority;
	}

	public function write_priority() {
		return $this->write_priority;
	}
}

function init() {
	if ( defined( 'WPINC' ) ) {
		// Don't do anything when WordPress is loaded.
		return;
	}

	if ( ! class_exists( 'WP_CLI' ) ) {
		return;
	}

	add_db_before_run_command( new Config() );
}

function get_database_server( Config $config ): DB_Server {
	global $db_servers;

	if ( ! is_array( $db_servers ) ) {
		throw new Exception( 'The database configuration is missing.' );
	}

	if ( empty( $db_servers ) ) {
		throw new Exception( 'The database configuration is empty.' );
	}

	$server_objects = array_map(
		fn ( $server_tuple ) => new DB_Server( ...$server_tuple ),
		$db_servers
	);

	$server_objects = array_filter( $server_objects, function ( $candidate ) use ( $config ) {
		return $candidate->can_read() && ! (
			$candidate->can_write() && ! $config->allow_writes()
		);
	} );

	// Sort the replicas in ascending order of the write priority (if allowed), else sort by read priority.
	usort( $server_objects, function ( $c0, $c1 ) use ( $config ) {
		if ( $config->allow_writes() ) {
			return $c0->write_priority() <=> $c1->write_priority();
		}
		return $c0->read_priority() <=> $c1->read_priority();
	} );

	return end( $server_objects );
}

function add_db_before_run_command( Config $config ) {
	WP_CLI::add_hook( 'before_run_command', function ( $command ) use ( $config ) {
		if ( ! ( isset( $command[0] ) && 'db' === $command[0] ) ) {
			// Don't do anything for any command other than `db`
			return;
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
		if ( ! $config->allow_writes() && in_array( $subcommand, $write_specific_subcommands ) ) {
			throw new Exception( "Error: The 'wp db $subcommand' subcommand is not currently allowed for this site." );
		}

		$server = get_database_server( $config );
		$server->define_variables();
		// TODO: Logging
	} );
}

init();
