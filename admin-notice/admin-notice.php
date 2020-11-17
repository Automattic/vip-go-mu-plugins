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
add_action( 'admin_enqueue_scripts', [ $admin_notice_controller, 'enqueue_scripts' ] );
add_action( 'wp_ajax_dismiss_vip_notice', [ $admin_notice_controller, 'dismiss_vip_notice' ] );

// WP 5.6 RC1 (released Nov 17, 2020)
$admin_notice_controller->add(
	new Admin_Notice(
		'WordPress 5.6 will be released on December 8th, 2020. Please ensure your sites have been tested against the current Release Candidate',
		[
			new Date_Condition( '2020-11-17', '2020-12-09' ),
			new WP_Version_Condition( '1.0.0', '5.6' ),
			new Capability_Condition( 'manage_options' ),
		],
		'wp-5.6-rc1'
) );
