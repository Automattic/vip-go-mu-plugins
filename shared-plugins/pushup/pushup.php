<?php

/**
 * Plugin Name: PushUp Notifications
 * Plugin URI:  http://pushupnotifications.com
 * Description: Desktop push notifications for your WordPress powered website, starting with OS X Mavericks.
 * Version:     1.2.2
 * Author:      10up
 * Author URI:  http://10up.com
 * License:     GPLv2 or later
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Include classes
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-core.php'               );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-json-api.php'           );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-authentication.php'     );
require_once( plugin_dir_path( __FILE__ ) . 'includes/class-push-notifications.php' );

// Instantiate objects
PushUp_Notifications_Core::instance();
PushUp_Notifications_JSON_API::instance();
PushUp_Notifications_Authentication::instance();
PushUp_Notifications::instance();
