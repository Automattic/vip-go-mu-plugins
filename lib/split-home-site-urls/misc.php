<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
* Ensure preview URLs are served over SSL
*/
function fix_preview_link_host( $link ) {
	$search = get_home_url( null, '/', is_ssl() ? 'https' : 'http' );

	return str_replace( $search, site_url( '/' ), $link );
}
add_filter( 'preview_post_link', __NAMESPACE__ . '\fix_preview_link_host' );
