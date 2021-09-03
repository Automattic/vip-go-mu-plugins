<?php

namespace Automattic\VIP\WooCommerce;

/*
 * The best option right now is to use the DB logging handler for WC logs by default,
 * as the files service is not currently ideal for the task.
 *
 * Due to performance concerns with database writes,
 * we need to add some protections around this functionality.
 */
if ( ! defined( 'WC_LOG_HANDLER' ) ) {
	define( 'WC_LOG_HANDLER', 'WC_Log_Handler_DB' );
}

if ( ! defined( 'WC_LOG_DIR' ) ) {
	// Fallback to writing to the /tmp directory, in case a plugin specifically tries to write to a log file manually.
	define( 'WC_LOG_DIR', '/tmp/' );
}

add_action( 'plugins_loaded', function() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_filter( 'woocommerce_logger_log_message', __NAMESPACE__ . '\restrict_woocommerce_logging', 30, 4 );
} );

/**
 * Add safety measures / restrictions for WooCommerce logging.
 *
 * @param string $message Log message. Returning `null` prevents logging.
 * @param string $level   One of: emergency, alert, critical, error, warning, notice, info, or debug.
 * @param array  $context Additional information for log handlers.
 * @param object $handler The handler object, such as WC_Log_Handler_File. Available since WC 5.3.
 */
function restrict_woocommerce_logging( $message, $level, $context, $handler = null ) {
	// Bail early if WC version is < 5.3.
	if ( null === $handler ) {
		return $message;
	}

	// Logging to /tmp/ is rather pointless - so just preventing the write altogether.
	if ( is_a( $handler, 'WC_Log_Handler_File' ) && '/tmp/' === WC_LOG_DIR ) {
		return null;
	}

	// Safety Measure #1: Mute some WooCommerce logging sources that do not need to be active by default.
	$muted_sources = apply_filters( 'vip_woocommerce_muted_logging_sources', [ 'webhooks-delivery', 'fatal-errors' ] );
	if ( is_a( $handler, 'WC_Log_Handler_DB' ) && isset( $context['source'] ) && in_array( $context['source'], $muted_sources, true ) ) {
		return null;
	}

	// Safety Measure #2: Setup a killswitch to quickly intervene if problems occur.
	if ( true === get_option( 'vip_woocommerce_prevent_debug_logging', false ) ) {
		return null;
	}

	return $message;
}
