<?php

require_once __DIR__ . '/../vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( '1' === getenv( 'VIP_JETPACK_SKIP_LOAD' ) ) {
	define( 'VIP_JETPACK_SKIP_LOAD', true );
}

require_once $_tests_dir . '/includes/functions.php';

define( 'VIP_GO_MUPLUGINS_TESTS__DIR__', __DIR__ );
define( 'WPMU_PLUGIN_DIR', getcwd() );

// Constant configs
// Ideally we'd have a way to mock these
define( 'FILES_CLIENT_SITE_ID', 123 );
define( 'WPCOM_VIP_MAIL_TRACKING_KEY', 'key' );
define( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING', true );


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

tests_add_filter( 'muplugins_loaded', '_disable_core_legacy_widget_registration' );

// Disable calls to wordpress.org to get translations
tests_add_filter( 'translations_api', function ( $res ) {
	if ( false === $res ) {
		$res = [ 'translations' => [] ];
	}

	return $res;
} );

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
		add_filter( 'wpvip_parsely_version', function () use ( $specified ) {
			return $specified;
		} );
	}
}

switch ( getenv( 'WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION' ) ) {
	case '2.6':
		tests_add_filter(
			'wpvip_parsely_version',
			function() {
				return '2.6';
			}
		);
		break;
	case '3.5':
	default:
		tests_add_filter(
			'wpvip_parsely_version',
			function() {
				return '3.5';
			}
		);
		break;
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

tests_add_filter( 'muplugins_loaded', function () {
	echo "[WP_PARSELY_INTEGRATION] Removing autoload (so we can manually test)\n";
	$removed = remove_action( 'plugins_loaded', 'Automattic\VIP\WP_Parsely_Integration\maybe_load_plugin', 1 );
	if ( ! $removed ) {
		throw new Exception( '[WP_PARSELY_INTEGRATION] Failed to remove autoload' );
	}
	echo "[WP_PARSELY_INTEGRATION] Disabling the telemetry backend\n";
	add_filter( 'wp_parsely_enable_telemetry_backend', '__return_false' );
} );

require_once __DIR__ . '/mock-constants.php';
require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/class-speedup-isolated-wp-tests.php';

require_once $_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/class-coverage-helper.php';

if ( isset( $GLOBALS['wp_version'] ) ) {
	echo PHP_EOL, 'WordPress version: ' . esc_html( $GLOBALS['wp_version'] ), PHP_EOL;
}
