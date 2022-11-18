<?php

namespace Automattic\VIP;

class Environment {

	/**
	 * The prefix for VIP user supplied environment variables
	 */
	const VAR_PREFIX = 'VIP_ENV_VAR';

	/**
	 * Checks existence of custom environment variable values based on the supplied key and return
	 * its name if it is set, null otherwise.
	 *
	 * @param string $key The name of the environment variable.
	 * @return string|null
	 */
	public static function has_var( string $key ): ?string {
		$key       = strtoupper( $key );
		$env_const = sprintf( '%s_%s', self::VAR_PREFIX, $key );

		return defined( $env_const ) ? $env_const : null;
	}

	/**
	 * Attempts to return custom environment variable values based on the supplied key
	 *
	 * @param string $key The name of the environment variable.
	 * @param string $default_value The value to return if no environment variable matches the key
	 * @return string
	 */
	public static function get_var( string $key, $default_value = '' ) {
		$env_const = static::has_var( $key );

		if ( null !== $env_const ) {
			return constant( $env_const );
		}

		// The call was not able to retrieve an env variable
		// log this as an E_USER_NOTICE for debugging
		trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			sprintf(
				'The ENV variable: "%s" did not exist. %s::get_var returned the default value',
				$key, // phpcs:ignore WordPress.Security.EscapeOutput
				__CLASS__
			),
			\E_USER_NOTICE
		);

		return $default_value;
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
