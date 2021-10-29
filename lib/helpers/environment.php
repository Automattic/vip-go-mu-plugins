<?php
/**
 * Helper functions pretaining to the environment domain
*/

use Automattic\VIP\Environment;

/**
 * A wrapper for Automattic\VIP\Environment::get_var
 */
function vip_get_env_var( $key, $default_value = '' ) {
	return Environment::get_var( $key, $default_value );
}