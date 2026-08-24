<?php
/**
 * PHPUnit bootstrap file
 */
require_once __DIR__ . '/../vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
$_core_dir  = getenv( 'WP_CORE_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! $_core_dir ) {
	$_core_dir = '/tmp/wordpress';
}

// Give access to tests_add_filter() function.
/** @psalm-suppress UnresolvableInclude */
require_once $_tests_dir . '/includes/functions.php';

// Copy across the drop-in plugin.
$fname   = dirname( __DIR__ ) . '/object-cache.php';
$content = "<?php require_once '{$fname}';";
file_put_contents( $_core_dir . '/wp-content/object-cache.php', $content ); // phpcs:ignore

// TODO: Test both scenarios during CI stuffs.
if ( ! defined( 'AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION' ) ) {
	define( 'AUTOMATTIC_MEMCACHED_USE_MEMCACHED_EXTENSION', true );
}

// Start up the WP testing environment.
/** @psalm-suppress UnresolvableInclude */
require $_tests_dir . '/includes/bootstrap.php';
