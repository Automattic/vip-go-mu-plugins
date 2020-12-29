<?php
namespace Automattic\VIP\Search;

// Bail early if VIP Search is not active to not waste resources
if ( ! ( defined( 'VIP_ENABLE_VIP_SEARCH' ) && VIP_ENABLE_VIP_SEARCH ) ) {
	return;
}

define( 'SEARCH_DEV_TOOLS_CAP', 'edit_others_posts' );

function should_enable_search_dev_tools() {
	return current_user_can( SEARCH_DEV_TOOLS_CAP );
}
