<?php

use Automattic\VIP\Utils\Context;

/**
 * Get the application name (slug).
 *
 * @return string The application slug (e.g., 'example-app'), or empty string if not defined.
 */
function wpvip_get_app_name(): string {
	return Context::get_app_slug();
}

/**
 * Get the application environment.
 *
 * @return string The application environment (e.g., 'production', 'develop', 'local'), or empty string if not defined.
 */
function wpvip_get_app_environment(): string {
	return Context::get_environment();
}

/**
 * Get the application alias in the format used by VIP-CLI.
 *
 * Returns the application alias in the format `<app-slug>.<environment>`,
 * which can be used to target environments with VIP-CLI commands.
 *
 * @see https://docs.wpvip.com/vip-cli/target-environments/#application-alias-by-name
 *
 * @return string The application alias (e.g., 'example-app.develop'), or empty string if either constant is not defined.
 */
function wpvip_get_app_alias(): string {
	$app_slug    = Context::get_app_slug();
	$environment = Context::get_environment();

	if ( '' === $app_slug || '' === $environment ) {
		return '';
	}

	return $app_slug . '.' . $environment;
}
