<?php

namespace Automattic\VIP\Admin_Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-admin-notice.php';
require_once __DIR__ . '/class-admin-notice-controller.php';
require_once __DIR__ . '/conditions/interface-condition.php';
require_once __DIR__ . '/conditions/class-date-condition.php';
require_once __DIR__ . '/conditions/class-capability-condition.php';
require_once __DIR__ . '/conditions/class-wp-version-condition.php';

$admin_notice_controller = new Admin_Notice_Controller();

add_action( 'admin_notices', [ $admin_notice_controller, 'display_notices' ] );
add_action( 'admin_print_styles', [ $admin_notice_controller, 'print_styles' ] );
add_action( 'admin_enqueue_scripts', [ $admin_notice_controller, 'enqueue_scripts' ] );

$admin_notice_controller->add(
	new Admin_Notice(
		'WordPress 5.5.2 will be released on Friday, October 30th',
		[
			new Date_Condition( '2020-07-01', '2020-10-30 15:00' ),
			new WP_Version_Condition( '5.5.1', '5.5.2' ),
		],
		'wp-5.5.2'
) );
