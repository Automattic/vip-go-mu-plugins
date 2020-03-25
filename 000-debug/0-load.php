<?php

namespace Automattic\VIP\Debug;

require_once( __DIR__ . '/logger.php' );
require_once( __DIR__ . '/debug-mode.php' );

function remove_background_updates_test( $tests ) {
 unset( $tests['async']['background_updates'] );
 return $tests;
}
add_filter( 'site_status_tests', 'remove_background_updates_test' );
