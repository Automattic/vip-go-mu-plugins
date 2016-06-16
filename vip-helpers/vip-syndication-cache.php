<?php

add_action( 'syn_after_setup_server', function() {
	if ( ! class_exists( 'WP_Feed_Cache' ) ) {
			require_once( ABSPATH . WPINC . '/class-feed.php' );
	}
} );
