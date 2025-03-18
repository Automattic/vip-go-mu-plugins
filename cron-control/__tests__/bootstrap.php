<?php
/**
 * PHPUnit bootstrap file.
 */

use Automattic\WP\Cron_Control;

require_once __DIR__ . '/../vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	define( 'WP_CRON_CONTROL_SECRET', 'testtesttest' );

	define(
		'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS',
		array(
			array(
				'schedule' => 'hourly',
				'action'   => 'cron_control_additional_internal_event',
				'callback' => '__return_true',
			),
		)
	);

	require dirname( dirname( __FILE__ ) ) . '/cron-control.php';

	// Plugin loads after `wp_install()` is called, so we compensate.
	if ( ! Cron_Control\Events_Store::is_installed() ) {
		Cron_Control\Events_Store::instance()->install();
		Cron_Control\register_adapter_hooks();
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Utilities.
require_once __DIR__ . '/utils.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Setup WP-CLI dependencies.
if ( ! defined( 'WP_CLI_ROOT' ) ) {
	define( 'WP_CLI_ROOT', __DIR__ . '/../vendor/wp-cli/wp-cli' );
}

include WP_CLI_ROOT . '/php/utils.php';
include WP_CLI_ROOT . '/php/dispatcher.php';
include WP_CLI_ROOT . '/php/class-wp-cli.php';
include WP_CLI_ROOT . '/php/class-wp-cli-command.php';

\WP_CLI\Utils\load_dependencies();

// WP_CLI wasn't defined during plugin bootup, so bootstrap our cli classes manually
require dirname( dirname( __FILE__ ) ) . '/includes/wp-cli.php';
Cron_Control\CLI\prepare_environment();
