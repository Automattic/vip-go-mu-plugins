<?php

add_action( 'syn_after_setup_server', function() {
	if ( ! class_exists( 'WP_Feed_Cache' ) && file_exists( ABSPATH . WPINC . '/class-wp-feed-cache.php' ) ) {
		if ( ! class_exists( 'SimplePie', false ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}

		require_once ABSPATH . WPINC . '/class-wp-feed-cache.php';
		require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';
		require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';
	}
} );
