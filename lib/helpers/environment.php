<?php
/**
 * Helper functions pertaining to the environment domain
*/

require_once __DIR__ . '/../environment/class-environment.php';

use Automattic\VIP\Environment;

/**
 * A wrapper for Automattic\VIP\Environment::get_var
 *
 * @param string $key The name of the environment variable.
 * @param mixed $default_value The value to return if no environment variable matches the key
 * @return mixed
 */
function vip_get_env_var( string $key, $default_value = '' ) {
	return Environment::get_var( $key, $default_value );
}

/**
 * A wrapper for Automattic\VIP\Environment::has_var.
 *
 * @param string $key The name of the environment variable.
 * @return bool
 */
function vip_has_env_var( string $key ): bool {
	return Environment::has_var( $key );
}
