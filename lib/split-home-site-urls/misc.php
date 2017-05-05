<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
* Ensure preview URLs are served over SSL
*/
function fix_preview_link_host( $link ) {
return str_replace( home_url( '/' ), site_url( '/' ), $link );
}
add_filter( 'preview_post_link', __NAMESPACE__ . '\fix_preview_link_host' );
