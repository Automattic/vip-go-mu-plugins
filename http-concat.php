<?php

/**
 * Plugin Name: HTTP Concat 
 * Description: A plugin to perform CSS and Javascript concatenation of individual scripts into a single script.
 * Author: Automattic
 * Version: 1.0
 */

// We don't want concat running outside VIP Go environments.
if ( true === WPCOM_IS_VIP_ENV ) {
	// Activate concatenation
	if ( ! isset( $_GET['concat_js'] ) || 'yes' === $_GET['concat_js'] ) {
		require __DIR__ .'/http-concat/jsconcat.php';
	}

	if ( ! isset( $_GET['concat_css'] ) || 'yes' === $_GET['concat_css'] ) {
		require __DIR__ .'/http-concat/cssconcat.php';
	}
}
