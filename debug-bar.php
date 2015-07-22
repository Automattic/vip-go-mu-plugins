<?php
/*
 Plugin Name: Debug Bar
 Plugin URI: http://wordpress.org/extend/plugins/debug-bar/
 Description: Adds a debug menu to the admin bar that shows query, cache, and other helpful debugging information.
 Author: wordpressdotorg
 Version: 0.8.2
 Author URI: http://wordpress.org/
 */

add_filter( 'debug_bar_enable', function( $enable ) {
    $enable = is_automattician();

    return $enable;
}, 99 );

// We only need to load the files if it's enabled
add_action( 'after_setup_theme', function() {
    $enable = apply_filters( 'debug_bar_enable', false );

    if ( ! $enable ) {
        return;
    }

    if ( ! defined( 'SAVEQUERIES' ) ) {
        define( 'SAVEQUERIES', true );

        // For hyperdb, which doesn't use SAVEQUERIES
        global $wpdb;

        $wpdb->save_queries = true;
    }

    require_once( __DIR__ . '/debug-bar/debug-bar.php' );

    // Setup extra panels
    add_filter( 'debug_bar_panels', function( $panels ) {
        // @todo, see wpcom for details

    	return $panels;
    }, 99);
}, 99 );
