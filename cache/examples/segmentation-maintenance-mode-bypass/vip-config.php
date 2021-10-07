<?php

// [...] other parts of vip-config/vip-config.php

function x_maybe_enable_maintenance_mode() {
	// Generate using something like `openssl rand -hex 40`.
	// This is a secret value shared with our test provider.
	$maintenance_bypass_secret = 'this-is-a-secret-value';

	// Enabled by default but disabled conditionally below.
	$enable_maintenance_mode = true;
	if ( ! defined( 'VIP_GO_ENV' ) ) {
		// Don't enable for non-VIP environments.
		$enable_maintenance_mode = false;
	} elseif ( isset( $_COOKIE['vip-go-seg'] )
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		&& hash_equals( $maintenance_bypass_secret, $_COOKIE['vip-go-seg'] ) ) {
		// Don't enable if the request has our secret key.
		$enable_maintenance_mode = false;
	}

	// Enable maintenance mode if needed.
	define( 'VIP_MAINTENANCE_MODE', $enable_maintenance_mode );

	// Make sure our reverse proxy respects the cookie.
	header( 'Vary: X-VIP-Go-Segmentation', false );
}

x_maybe_enable_maintenance_mode();
