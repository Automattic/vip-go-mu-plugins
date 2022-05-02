<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

class Config {
	private bool $enabled = false;
	private bool $allow_writes = false;

	public function __construct() {
		$this->enabled = defined( 'VIP_ENV_VAR_WP_DB_ENABLED' ) && '1' === constant( 'VIP_ENV_VAR_WP_DB_ENABLED' );
		$this->allow_writes = defined( 'VIP_ENV_VAR_WP_DB_ALLOW_WRITES' ) && '1' === constant( 'VIP_ENV_VAR_WP_DB_ALLOW_WRITES' );
	}

	public function enabled(): bool {
		return $this->enabled;
	}

	public function allow_writes(): bool {
		return $this->allow_writes;
	}
}
