<?php
/*
 * Plugin Name: Debug Loader
 * Description: A set of debug tools and plugins
 */


/**
 * Test Jquery Updates
 *
 * There are multiple versions per WordpressVersion the loader tries to pick the most recent one
 */
if ( ( defined( 'VIP_ENABLE_TEST_JQUERY_UPDATES' ) && VIP_ENABLE_TEST_JQUERY_UPDATES ) ) {
	$base_folder = __DIR__ . '/debug/test-jquery-updates';
	$entrypoint_script = 'wp-jquery-update-test.php';

	$avaiable_options = array_diff( scandir( $base_folder ), [ '.', '..' ] );

	$best_fit;
	foreach ( $avaiable_options as $candidate ) {
		if ( version_compare( $GLOBALS['wp_version'], $candidate, '>=' ) ) {
			if ( ! $best_fit || version_compare( $candidate, $best_fit, '>' ) ) {
				$best_fit = $candidate;
			}
		}
	}
	require_once( "$base_folder/$best_fit/$entrypoint_script" );
}
