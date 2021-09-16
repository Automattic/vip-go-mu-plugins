<?php
/*
 * Plugin Name: WooCommerce: VIP Specific Changes
 * Description: VIP-specific customizations for WooCommerce.
 * Author: Automattic
 * Version: 1.0.0
 * License: GPL2+
 */

require_once __DIR__ . '/logging.php';

if ( file_exists( __DIR__ . '/action-scheduler.php' ) ) {
	require_once __DIR__ . '/action-scheduler.php';
}
