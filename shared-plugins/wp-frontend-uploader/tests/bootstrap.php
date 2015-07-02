<?php
require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', create_function( '', "require dirname( __FILE__ ) . '/../frontend-uploader.php';" ) );

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
