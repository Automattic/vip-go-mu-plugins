<?php

/**
 * Plugin Name: VIP Jetpack Start
 * Description: Jetpack connections made easy; just put your key in the ignition and go!
 * Version: 0.1
 * Author: Automattic
 */

// Jetpack Start can triggered via A8C servers only
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( __DIR__ . '/wp-cli-keys.php' );
	require_once( __DIR__ . '/wp-cli-api.php' );
}
