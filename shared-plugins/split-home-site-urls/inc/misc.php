<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
* Ensure preview URLs are served over SSL
*/
function fix_url_host( $url ) {
	$search = get_home_url( null, '/', is_ssl() ? 'https' : 'http' );

	return str_replace( $search, site_url( '/' ), $url );
}
add_filter( 'preview_post_link', __NAMESPACE__ . '\fix_url_host' );

/**
 * Ensure REST API requests are secure and respect cookies
 *
 * REST API uses home_url() by default
 */
if ( is_admin() ) {
	add_filter( 'rest_url', __NAMESPACE__ . '\fix_url_host', 99 );
}
