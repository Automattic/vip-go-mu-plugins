<?php
/*
 * Plugin Name: Debug Loader
 * Description: A set of debug tools and plugins
 */


/**
 * Test Jquery Updates
 *
 * There are multiple versions per WordPress version the loader tries to pick the most recent one
 */
$has_test_jquery_param = isset( $_GET['vip_load_test_jquery_updates'] );
$has_test_jquery_constant = defined( 'VIP_ENABLE_TEST_JQUERY_UPDATES' ) && true === VIP_ENABLE_TEST_JQUERY_UPDATES;
if ( $has_test_jquery_param || $has_test_jquery_constant ) {
	$base_folder = __DIR__ . '/debug/test-jquery-updates';
	$entrypoint_script = 'wp-jquery-update-test.php';
	$version = 'unkown';
	$settings_key = 'wp-jquery-test-settings';
	$default_settings = [];

	if ( version_compare( $GLOBALS['wp_version'], '5.6-alpha', '<' ) ) {
		$version = '5.5';
		$default_settings = [
			'version'   => '3.5.1',
			'migrate'   => 'enable',
			'uiversion' => '1.12.1',
		];
	} else {
		$version = '5.6';
		$default_settings = [
			'version'   => 'default',
			'migrate'   => 'enable',
		];
	}

	require_once( "$base_folder/$version/$entrypoint_script" );

	$existing_settings = get_site_option( $settings_key );
	if ( ! $existing_settings ) {
		update_site_option( 'wp-jquery-test-settings', $default_settings );
	}
}

unset( $has_test_jquery_param, $has_test_jquery_constant );
