<?php

if ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) {

	// Bail if this is a VIP Go Environment
	return;
}

/**
 * DISABLE_WP_CRON: false
 *
 * Explicitly set DISABLE_WP_CRON to false so WP_CRON_CONTROL
 * doesn't disable wp-cron in local environments.
 */
define( 'DISABLE_WP_CRON', false );
