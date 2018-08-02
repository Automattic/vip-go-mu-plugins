<?php

/**
 * Plugin Name: VIP Go Core Modifications
 * Description: Changes to make WordPress core work better on VIP Go.
 */

require_once( __DIR__ . '/001-core/privacy.php' );

if ( false !== WPCOM_IS_VIP_ENV ) {
	add_action( 'muplugins_loaded', 'wpcom_vip_init_core_restrictions' );
}

function wpcom_vip_init_core_restrictions() {
	add_action( 'admin_init', 'wpcom_vip_disable_core_update_nag' );
	add_filter( 'map_meta_cap', 'wpcom_vip_disable_core_update_cap', 100, 2 );
}

function wpcom_vip_disable_core_update_nag() {
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
}

function wpcom_vip_disable_core_update_cap( $caps, $cap ) {
	if ( 'update_core' === $cap ) {
		$caps = [ 'do_not_allow' ];
	}
	return $caps;
}

/**
 * Remove Try Gutenberg callout introduced as part of 4.9.8
 */
remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
