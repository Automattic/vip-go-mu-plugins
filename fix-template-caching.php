<?php
/**
 * Plugin name: WP Trac #42573: Fix for theme template file caching.
 * Description: Flush the theme file cache each time the admin screens are loaded which uses the file list.
 * Author: Weston Ruter, XWP.
 * Plugin URI: https://core.trac.wordpress.org/ticket/42573
 */

if ( false === function_exists( 'wp_42573_fix_template_caching' ) ) { // VIP: adding function exists check in order to prevent clashes with local versions of the plugin.
	function wp_42573_fix_template_caching( WP_Screen $current_screen ) {

		// Only flush the file cache with each request to post list table, edit post screen, or theme editor.
		if ( ! in_array( $current_screen->base, array( 'post', 'edit', 'theme-editor' ), true ) ) {
			return;
		}

		$theme = wp_get_theme();
		if ( ! $theme ) {
			return;
		}

		$cache_hash = md5( $theme->get_theme_root() . '/' . $theme->get_stylesheet() );
		$label = sanitize_key( 'files_' . $cache_hash . '-' . $theme->get( 'Version' ) );
		$transient_key = substr( $label, 0, 29 ) . md5( $label );
		delete_transient( $transient_key );
	}
	add_action( 'current_screen', 'wp_42573_fix_template_caching' );
}
