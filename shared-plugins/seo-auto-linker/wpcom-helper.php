<?php
/**
 * Restrict the number of links that can be added to the blacklist
 * because we don't want to blow up options
 */
add_filter( 'seoal_blacklist_max', 'wpcom_vip_seoal_blacklist_max' );
function wpcom_vip_seoal_blacklist_max( $orig ) {
	return 30;
}

/**
 * Restrict the number of links that are possibly added to each post
 */
add_filter( 'seoal_number_links', 'wpcom_vip_seoal_number_links' );
function wpcom_vip_seoal_number_links( $orig ) {
	return 100;
}