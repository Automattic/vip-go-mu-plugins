<?php

/**
 * Plugin Name: HTTP Concat 
 * Description: A plugin to perform CSS and Javascript concatenation of individual scripts into a single script.
 * Author: Automattic
 * Version: 1.0
 */

if ( ! defined( 'VIP_GO_ENABLE_HTTP_CONCAT' ) ) {
	define( 'VIP_GO_ENABLE_HTTP_CONCAT', true === WPCOM_IS_VIP_ENV );
}

if ( true === VIP_GO_ENABLE_HTTP_CONCAT ) {
	// Activate concatenation
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['concat_js'] ) || 'yes' === $_GET['concat_js'] ) {
		require __DIR__ . '/http-concat/jsconcat.php';

		add_filter( 'js_do_concat', function( $do_concat, $handle ) {
			// Retain < 5.0 behaviour for tinyMCE scripts.
			// These used to be output individually and bypass concat.
			// 5.0 registers them as scripts which gets them picked up by out concat.
			// However, tinymce.min.js (root) does not handle that situation well.
			// It ends up initializing with the baseURL set to that of the script.
			// Which then leads to problems loading additional dependencies.
			if ( 'wp-tinymce-root' === $handle || 'wp-tinymce' === $handle ) {
				$do_concat = false;
			}

			return $do_concat;
		}, 10, 2 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['concat_css'] ) || 'yes' === $_GET['concat_css'] ) {
		require __DIR__ . '/http-concat/cssconcat.php';
	}
}
