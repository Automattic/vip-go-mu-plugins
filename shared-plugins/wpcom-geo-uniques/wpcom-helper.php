<?php

add_action( 'wpcom_geo_uniques_locate_user', function( $location ) {
	if ( function_exists( 'bump_stats_extras' ) ) {
		bump_stats_extras( 'wpcom-geo-uniques-locate-user-by-theme', str_replace( '/', '-', get_stylesheet() ) );
	}
} );

