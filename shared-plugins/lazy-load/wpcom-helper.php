<?php

add_filter( 'lazyload_is_enabled', 'wpcom_vip_disable_lazyload_on_mobile' );

function wpcom_vip_disable_lazyload_on_mobile( $enabled ) {
	if ( function_exists( 'jetpack_is_mobile' ) && jetpack_is_mobile() )
		$enabled = false;

	if ( class_exists( 'Jetpack_User_Agent_Info' ) && Jetpack_User_Agent_Info::is_ipad() )
		$enabled = false;

	return $enabled;
} 
