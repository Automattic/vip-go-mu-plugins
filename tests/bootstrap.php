<?php

require_once __DIR__ . '/../vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

define( 'VIP_GO_MUPLUGINS_TESTS__DIR__', __DIR__ );
define( 'WPMU_PLUGIN_DIR', getcwd() );

// Constant configs
// Ideally we'd have a way to mock these
define( 'FILES_CLIENT_SITE_ID', 123 );
define( 'WPCOM_VIP_MAIL_TRACKING_KEY', 'key' );
define( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING', true );

function _manually_load_plugin() {
	require_once __DIR__ . '/../000-vip-init.php';
	require_once __DIR__ . '/../001-core.php';
	require_once __DIR__ . '/../a8c-files.php';

	require_once __DIR__ . '/../async-publish-actions.php';
	require_once __DIR__ . '/../performance.php';

	require_once __DIR__ . '/../security.php';

	require_once __DIR__ . '/../schema.php';

	require_once __DIR__ . '/../vip-jetpack/vip-jetpack.php';

	// Proxy lib
	require_once __DIR__ . '/proxy-helpers.php'; // Needs to be included before ip-forward.php
	require_once __DIR__ . '/../lib/proxy/ip-forward.php';
	require_once __DIR__ . '/../lib/proxy/class-iputils.php';

	require_once __DIR__ . '/../vip-cache-manager.php';
	require_once __DIR__ . '/../vip-mail.php';
	require_once __DIR__ . '/../vip-rest-api.php';
	require_once __DIR__ . '/../vip-plugins.php';

	require_once __DIR__ . '/../wp-cli.php';

	require_once __DIR__ . '/../z-client-mu-plugins.php';
}

/**
 * VIP Cache Manager can potentially pollute other tests,
 * So we explicitly unhook the init callback.
 *
 */
function _remove_init_hook_for_cache_manager() {
	remove_action( 'init', array( WPCOM_VIP_Cache_Manager::instance(), 'init' ) );
}

/**
 * Core functionality causes `WP_Block_Type_Registry::register was called <strong>incorrectly</strong>. Block type "core/legacy-widget" is already registered. 
 *
 * Temporarily unhook it.
 *
 * @return void
 */
function _disable_core_legacy_widget_registration() {
	remove_action( 'init', 'register_block_core_legacy_widget', 20 );
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
tests_add_filter( 'muplugins_loaded', '_remove_init_hook_for_cache_manager' );
tests_add_filter( 'muplugins_loaded', '_disable_core_legacy_widget_registration' );

// Begin wp-parsely integration config
function _configure_wp_parsely_env_load_via_filter() {
	echo "[WP_PARSELY_INTEGRATION] Enabling the plugin via filter\n";
	add_filter( 'wpvip_parsely_load_mu', '__return_true' );
}

function _configure_wp_parsely_env_load_via_option() {
	echo "[WP_PARSELY_INTEGRATION] Enabling the plugin via option\n";
	update_option( '_wpvip_parsely_mu', '1' );
}

function _configure_wp_parsely_specified_version() {
	$specified = getenv( 'WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION' );
	if ( $specified ) {
		echo '[WP_PARSELY_INTEGRATION] Specifying plugin version: ' . esc_html( $specified ) . "\n";
		add_filter( 'wpvip_parsely_version', function ( $passed ) use ( $specified ) {
			return $specified;
		} );
	}
}

switch ( getenv( 'WPVIP_PARSELY_INTEGRATION_TEST_MODE' ) ) {
	case 'filter_enabled':
		echo "Expecting wp-parsely plugin to be enabled by the filter.\n";
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_env_load_via_filter' );
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_specified_version' );
		break;
	case 'option_enabled':
		echo "Expecting wp-parsely plugin to be enabled by the option.\n";
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_env_load_via_option' );
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_specified_version' );
		break;
	case 'filter_and_option_enabled':
		echo "Expecting wp-parsely plugin to be enabled by the filter and the option.\n";
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_env_load_via_filter' );
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_env_load_via_option' );
		tests_add_filter( 'muplugins_loaded', '_configure_wp_parsely_specified_version' );
		break;
	default:
		echo "Expecting wp-parsely plugin to be disabled.\n";
		break;
}

require_once __DIR__ . '/mock-constants.php';
require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/class-speedup-isolated-wp-tests.php';

require $_tests_dir . '/includes/bootstrap.php';
