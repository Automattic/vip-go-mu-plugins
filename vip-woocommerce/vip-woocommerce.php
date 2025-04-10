<?php
/*
 * Plugin Name: WooCommerce: VIP Specific Changes
 * Description: VIP-specific customizations for WooCommerce.
 * Author: Automattic
 * Version: 1.0.0
 * License: GPL2+
 */

// Note: Special Action Scheduler functionality can be found in /cron/action-scheduler-dynamic-queue.php.

require_once __DIR__ . '/logging.php';

// The VIP file stream wrapper causes a safety check to fail in wc_is_file_valid_csv() during product csv imports.
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_filter( 'woocommerce_csv_importer_check_import_file_path', function ( $check_import_file_path, $file ) {
		$is_vip_stream_wrapper = str_starts_with( $file, 'vip://' );

		if ( $is_vip_stream_wrapper ) {
			// Returning false will avoid the file path check.
			return false;
		}

		return $check_import_file_path;
	}, 10, 2 );
} );
