<?php
/*
 * Plugin Name: WooCommerce: VIP Specific Changes
 * Description: VIP-specific customizations for WooCommerce.
 * Author: Automattic
 * Version: 1.0.0
 * License: GPL2+
 */

if ( file_exists( __DIR__ . '/vip-woocommerce/vip-woocommerce.php' ) ) {
	require_once( __DIR__ . '/vip-woocommerce/vip-woocommerce.php' );
}
