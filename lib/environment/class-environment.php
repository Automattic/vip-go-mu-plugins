<?php

namespace Automattic\VIP;

class Environment {

	/**
	 * The prefix for VIP user supplied environment variables
	 */
	const VAR_PREFIX = 'VIP_ENV_VAR';

	/**
	 * Attempts to return custom environment variable values based on the supplied key
	 *
	 * @param string $key The name of the environment variable.
	 * @param string $default_value The value to return if no environment variable matches the key
	 * @return string
	 */
	public static function get_var( string $key, $default_value = '' ) {
		$key       = strtoupper( $key );
		$env_const = sprintf( '%s_%s', self::VAR_PREFIX, $key );

		// First check to see if the VIP_ENV_VAR constant exists and return it
		// If constant does not exist fallback to const without the prefix
		// If neither constant exist, return default
		if ( defined( $env_const ) ) {
			return constant( $env_const );
		} elseif ( defined( $key ) ) {
			// interim migration step
			return constant( $key );
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
