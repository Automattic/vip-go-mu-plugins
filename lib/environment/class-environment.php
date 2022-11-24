<?php

namespace Automattic\VIP;

class Environment {

	/**
	 * The prefix for VIP user supplied environment variables
	 */
	const VAR_PREFIX = 'VIP_ENV_VAR';

	/**
	 * Checks existence of custom environment variable values based on the supplied key.
	 *
	 * @param string $key The name of the environment variable.
	 * @return bool
	 */
	public static function has_var( string $key ): bool {
		return defined( self::calculate_env_const_name( $key ) );
	}

	/**
	 * Attempts to return custom environment variable values based on the supplied key.
	 *
	 * @param string $key The name of the environment variable.
	 * @param mixed $default_value The value to return if no environment variable matches the key
	 * @return mixed
	 */
	public static function get_var( string $key, $default_value = '' ) {
		$env_const = self::calculate_env_const_name( $key );

		return defined( $env_const ) ? constant( $env_const ) : $default_value;
	}

	/**
	 * Calculates environment variable constant name based on the supplied key.
	 *
	 * @param string $key
	 * @return string
	 */
	private static function calculate_env_const_name( string $key ): string {
		return sprintf( '%s_%s', self::VAR_PREFIX, strtoupper( $key ) );
	}

	public static function is_sandbox_container( $hostname, $env = array() ) {
		if ( false !== strpos( $hostname, '_web_dev_' ) || false !== strpos( $hostname, '-sbx-u' ) ) {
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
