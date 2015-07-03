<?php

add_action( 'postrelease_activate', 'wpcom_postrelease_activate_flush_rules' );

function wpcom_postrelease_activate_flush_rules() {
	// TODO: we should have a standalone function for flushing rules on WP.com, in VIP helpers
	if ( function_exists( 'rri_wpcom_flush_rules' ) )
		rri_wpcom_flush_rules();	
}
