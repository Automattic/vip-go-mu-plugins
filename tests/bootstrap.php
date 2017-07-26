<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

// Constant configs
// Ideally we'd have a way to mock these
define( 'FILES_CLIENT_SITE_ID', 123 );
define( 'WPCOM_VIP_MAIL_TRACKING_KEY', 'key' );

function _manually_load_plugin() {
	require_once( __DIR__ . '/../000-vip-init.php' );
	require_once( __DIR__ . '/../001-core.php' );
	require_once( __DIR__ . '/../a8c-files.php' );
	require_once( __DIR__ . '/../performance.php' );

	require_once( __DIR__ . '/../schema.php' );

	// Proxy lib
	require_once( __DIR__ . '/../lib/proxy/ip-forward.php' );
	require_once( __DIR__ . '/../lib/proxy/ip-utils.php' );

	require_once( __DIR__ . '/../vip-cache-manager.php' );
	require_once( __DIR__ . '/../vip-mail.php' );
	require_once( __DIR__ . '/../vip-rest-api.php' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
