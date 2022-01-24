<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration {

	use Yoast\WPTestUtils\WPIntegration;

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$_tests_dir = getenv( 'WP_TESTS_DIR' );

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	if ( ! $_tests_dir ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
		putenv( 'WP_TESTS_DIR=' . $_tests_dir );
	}

	if ( getenv( 'WP_PLUGIN_DIR' ) !== false ) {
		define( 'WP_PLUGIN_DIR', getenv( 'WP_PLUGIN_DIR' ) );
	} else {
		define( 'WP_PLUGIN_DIR', dirname( __DIR__, 3 ) );
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$GLOBALS['wp_tests_options'] = array(
		'active_plugins' => array( 'wp-parsely/wp-parsely.php' ),
	);

	require_once dirname( __DIR__ ) . '/../vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

	/*
	 * Load WordPress, which will load the Composer autoload file, and load the MockObject autoloader after that.
	 */
	WPIntegration\bootstrap_it();

	if ( ! defined( 'WP_PLUGIN_DIR' ) || file_exists( WP_PLUGIN_DIR . '/wp-parsely/wp-parsely.php' ) === false ) {
		echo PHP_EOL, 'ERROR: Please check whether the WP_PLUGIN_DIR environment variable is set and set to the correct value. The unit test suite won\'t be able to run without it.', PHP_EOL;
		exit( 1 );
	}

	// Include the Parsely custom test cases.
	require_once __DIR__ . '/TestCase.php';
	require_once __DIR__ . '/StructuredData/NonPostTestCase.php';
}

// Plugin root file is not included during tests, so define the namespaced constants here.
namespace Parsely {
	const PARSELY_VERSION = '123456.78.9';
	const PARSELY_FILE    = __DIR__ . '/../../wp-parsely.php';
}
