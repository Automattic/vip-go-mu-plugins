<?php
/*
Plugin Name: SocialFlow
Description: SocialFlow's WordPress plugin enhances your WordPress experience by allowing you to utilize the power of SocialFlow from right inside WordPress.
Author: SocialFlow, Dizzain
Version: 2.5.2
Author URI: http://socialflow.com/
Plugin URI: http://wordpress.org/plugins/socialflow/
License: GPLv2 or later
Text Domain: socialflow
Domain Path: /i18n
*/

/**
 * Current plugin version
 * Each time on plugin initialization 
 * we are checking this version with one stored in plugin options
 * and if they don't match plugin update hook will be fired
 *
 * @since 2.1
 */
if ( !defined( 'SF_VERSION' ) )
	define( 'SF_VERSION', '2.5.2' );

/**
 * The name of the SocialFlow Core file
 *
 * @since 2.0
 */
if ( !defined( 'SF_FILE' ) )
	define( 'SF_FILE', __FILE__ );

/**
 * Absolute location of SocialFlow Plugin
 *
 * @since 2.0
 */
if ( !defined( 'SF_ABSPATH' ) )
	define( 'SF_ABSPATH', dirname( SF_FILE ) );

/**
 * The name of the SocialFlow directory
 *
 * @since 2.0
 */
if ( !defined( 'SF_DIRNAME' ) )
	define( 'SF_DIRNAME', basename( SF_ABSPATH ) );

/**
 * Define Consumer Key
 *
 * @since 1.0
 */
define( 'SF_KEY', 'acbe74e2cc182d888412' );

/**
 * Define Consumer Secret
 *
 * @since 1.0
 */
define( 'SF_SECRET', '650108a50ea3cb2bd6f9' );


// Require plugin essential files
require_once( SF_ABSPATH . '/includes/class-socialflow-methods.php' );
require_once( SF_ABSPATH . '/includes/class-socialflow.php' );
require_once( SF_ABSPATH . '/includes/class-socialflow-admin.php' );
require_once( SF_ABSPATH . '/includes/class-socialflow-post.php' );
require_once( SF_ABSPATH . '/includes/class-socialflow-accounts.php' );
require_once( SF_ABSPATH . '/includes/class-socialflow-update.php' );

// Additional plugin classes
require_once( SF_ABSPATH . '/includes/class-plugin-options.php' );
require_once( SF_ABSPATH . '/includes/class-plugin-view.php' );

/**
 * SocialFlow object
 * @global object $socialflow
 * @since 2.0
 */
$GLOBALS['socialflow'] = new SocialFlow();