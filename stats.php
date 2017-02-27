<?php

namespace Automattic\VIP\Stats;

// Limit tracking to production
if ( true === WPCOM_IS_VIP_ENV ) {
	add_action( 'transition_post_status', __NAMESPACE__ . '\track_publish_post', 9999, 2 );
}

/**
 * Count publish events regardless of post type
 */
function track_publish_post( $new_status, $old_status ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	$pixel = add_query_arg( array(
		'v'                     => 'wpcom-no-pv',
		'x_vip-go-publish-post' => FILES_CLIENT_SITE_ID,
	), 'http://pixel.wp.com/b.gif' );

	wp_remote_get( $pixel, array(
		'blocking' => false,
		'timeout'  => 1,
	) );
}
