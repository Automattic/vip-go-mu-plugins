<?php
/**
 * This is a list of performance tweaks that will become default for All VIP sites
 */
function wpcom_vip_enable_performance_tweaks() {
	/**
	 * Improves performance of all the wp-admin pages that load comment counts in the menu.
	 * 
	 * This caches them for 30 minutes. It does not impact the per page comment count, only
	 * the total comment count that shows up in the admin menu.
	 */
	if ( function_exists( 'wpcom_vip_enable_cache_full_comment_counts' ) ) {
		wpcom_vip_enable_cache_full_comment_counts();
	}

	// This disables the adjacent_post links in the header that are almost never beneficial and are very slow to compute.
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );

	if ( function_exists( 'wpcom_vip_enable_old_slug_redirect_caching' ) ) {
		wpcom_vip_enable_old_slug_redirect_caching();
	}

	if ( function_exists( 'wpcom_vip_enable_maybe_skip_old_slug_redirect' ) ) {
		wpcom_vip_enable_maybe_skip_old_slug_redirect();
	}
}
add_action( 'after_setup_theme', 'wpcom_vip_enable_performance_tweaks' );

/**
 * Use this function to disable the loading of performance tweaks
 */
function wpcom_vip_disable_performance_tweaks() {
	remove_action( 'after_setup_theme', 'wpcom_vip_enable_performance_tweaks' );
}
