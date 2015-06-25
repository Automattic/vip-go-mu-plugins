<?php

// Disallow script access from outside WordPress's direct request.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

delete_option( 'findthebest_options' );
