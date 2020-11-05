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

require_once __DIR__ . '/class-admin-notice-controller.php';

Admin_Notice_Controller::init();
