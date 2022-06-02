<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

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
