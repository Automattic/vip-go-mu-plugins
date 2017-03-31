<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require __DIR__ . '/../000-vip-init.php';
	require __DIR__ . '/../001-core.php';
	require __DIR__ . '/../a8c-files.php';
	require __DIR__ . '/../performance.php';
	require __DIR__ . '/../security.php';

	require __DIR__ . '/../schema.php';

	// Proxy lib
	require __DIR__ . '/../lib/proxy/ip-forward.php';
	require __DIR__ . '/../lib/proxy/ip-utils.php';

	require __DIR__ . '/../vip-rest-api.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
