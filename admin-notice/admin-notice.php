<?php
/**
 * Plugin Name: Admin Notice
 * Description: Adds dismisable notice in admin area
 * Version:     0.1.0
 * Author:      Automattic VIP
 * Author URI:  https://wpvip.com
 * License:     GPLv2 or later
 *
 * @package Automattic\VIP\Admin_Notice
 */

namespace Automattic\VIP\Admin_Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-admin-notice.php';
require_once __DIR__ . '/class-admin-notice-controller.php';

$admin_notice_controller = new Admin_Notice_Controller();
$admin_notice_controller->init();

$admin_notice_controller->add( new Admin_Notice( 'WordPress 5.5.2 will be released on Friday, October 30th', '01-07-2020', '30-10-2020 15:00' ) );
