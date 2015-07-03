<?php

define( 'ES_WP_QUERY_TEST_ENV', true );

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../es-wp-query.php';

	if ( file_exists( dirname( __FILE__ ) . '/es.php' ) ) {
		require_once( dirname( __FILE__ ) . '/es.php' );
	} elseif ( getenv( 'TRAVIS' ) ) {
		es_wp_query_load_adapter( 'travis' );
	} else {
		echo "\n\nSetup Required\n"
			. "===========================================================\n"
			. "You must add an adapter to the plugin for this to work.\n"
			. "You can add it to the plugin in es-wp-query/tests/es.php\n"
			. "or elsewhere in your code using es_wp_query_load_adapter().\n"
			. "See the readme for more details.\n\n";
		exit( 1 );
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
