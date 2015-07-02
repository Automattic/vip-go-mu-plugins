<?php

// Sync is disabled so we don't need to run comment queries in the admin
wpcom_vip_load_plugin( 'disable-comments-query' );

/**
 * If the current post hasn't been published, don't load Disqus.
 *
 * @param bool $retval If Disqus thinks it should load itself
 * @return bool True if Disqus can load
 */
function wpcom_vip_dsq_can_replace( $retval ) {
	global $post;

	if ( ! in_array( $post->post_status, array( 'publish', 'private', 'inherit' ) ) )
		return false;

	return $retval;
}
add_filter( 'dsq_can_replace', 'wpcom_vip_dsq_can_replace' );
