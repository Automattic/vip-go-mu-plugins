<?php

namespace Automattic\VIP;

class Environment {
	public static function is_sandbox_container( $hostname, $env = array() ) {
		if ( false !== strpos( $hostname, '_web_dev_' ) ) {
			return true;
		}

		if ( isset( $env['IS_VIP_SANDBOX_CONTAINER'] ) && 'true' === $env['IS_VIP_SANDBOX_CONTAINER'] ) {
			return true;
		}

		return false;
	}

	public static function is_batch_container( $hostname, $env = array() ) {
		if ( false !== strpos( $hostname, '_wpcli_' ) || false !== strpos( $hostname, '_wp_cli_' ) ) {
			return true;
		}

		if ( isset( $env['IS_VIP_BATCH_CONTAINER'] ) && 'true' === $env['IS_VIP_BATCH_CONTAINER'] ) {
			return true;
		}

		return false;
	}
}
