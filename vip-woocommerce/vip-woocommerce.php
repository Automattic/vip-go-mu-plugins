<?php
/*
 * Plugin Name: WooCommerce: VIP Specific Changes
 * Description: VIP-specific customizations for WooCommerce.
 * Author: Automattic
 * Version: 1.0.0
 * License: GPL2+
 */

if ( ! defined( 'WC_LOG_HANDLER' ) ) {
	// Use the WooCommerce DB handler for logs by default.
	// Prevents issues with the files service, and is more persistent than logging to the /tmp directory.
	define( 'WC_LOG_HANDLER', 'WC_Log_Handler_DB' );
}

if ( ! defined( 'WC_LOG_DIR' ) ) {
	// As a fallback, write WC logs to the /tmp directory to prevent them from being copied to the files service.
	// Mainly just in case the DB handler is not used by a plugin.
	define( 'WC_LOG_DIR', '/tmp/' );
}

// NOTE: When possible, only load WC-specific logic when necessary.
add_action( 'plugins_loaded', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Default to keeping 14 days of logs in the database.
	// Cleans up via the woocommerce_cleanup_logs cron job.
	add_filter( 'woocommerce_logger_days_to_retain_logs', function() {
		return 14;
	} );
} );
