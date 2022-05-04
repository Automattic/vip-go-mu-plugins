<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

class Config {
	private bool $enabled = false;
	private bool $allow_writes = false;

	public function __construct() {
		$this->enabled = defined( 'WPVIP_ENABLE_WP_DB' ) && 1 === constant( 'WPVIP_ENABLE_WP_DB' );
		$this->allow_writes = defined( 'WPVIP_ENABLE_WP_DB_WRITES' ) && 1 === constant( 'WPVIP_ENABLE_WP_DB_WRITES' );
	}

	public function enabled(): bool {
		return $this->enabled;
	}

	public function allow_writes(): bool {
		return $this->allow_writes;
	}
}
