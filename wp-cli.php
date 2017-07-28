<?php

/**
 * Plugin Name: WP-CLI for VIP Go
 * Description: Scripts for VIP Go
 * Author: Automattic
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

foreach ( glob( __DIR__ . '/wp-cli/*.php' ) as $command ) {
	require( $command );
}
