<?php
/**
 * Plugin Name:  Syndication
 * Plugin URI:   http://wordpress.org/extend/plugins/push-syndication/
 * Description:  Syndicate content to and from your sites
 * Version:      2.0
 * Author:       Automattic
 * Author URI:   http://automattic.com
 * License:      GPLv2 or later
 */

define( 'SYNDICATION_VERSION', 2.0 );

if ( ! defined( 'PUSH_SYNDICATE_KEY' ) )
	define( 'PUSH_SYNDICATE_KEY', 'PUSH_SYNDICATE_KEY' );

require_once ( dirname( __FILE__ ) . '/includes/class-wp-push-syndication-server.php' );

if ( defined( 'WP_CLI' ) && WP_CLI )
	require_once( dirname( __FILE__ ) . '/includes/class-wp-cli.php' );

$push_syndication_server = new WP_Push_Syndication_Server;
