<?php

function wpcom_vip_disable_core_update_nag() {
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
}
add_action( 'admin_init', 'wpcom_vip_disable_core_update_nag' );

function wpcom_vip_disable_core_update_cap( $caps, $cap ) {
	if ( 'update_core' === $cap ) {
		$caps = [ 'do_not_allow' ];
	}
	return $caps;
}
add_filter( 'map_meta_cap', 'wpcom_vip_disable_core_update_cap', 100, 2 );
