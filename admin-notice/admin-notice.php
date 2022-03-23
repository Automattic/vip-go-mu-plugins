<?php

namespace Automattic\VIP\Admin_Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/class-admin-notice.php';
require_once __DIR__ . '/class-admin-notice-controller.php';
require_once __DIR__ . '/conditions/interface-condition.php';
require_once __DIR__ . '/conditions/class-expression-condition.php';
require_once __DIR__ . '/conditions/class-date-condition.php';
require_once __DIR__ . '/conditions/class-capability-condition.php';
require_once __DIR__ . '/conditions/class-wp-version-condition.php';
require_once __DIR__ . '/conditions/class-constant-condition.php';

$admin_notice_controller = new Admin_Notice_Controller();

add_action( 'admin_notices', [ $admin_notice_controller, 'display_notices' ] );
add_action( 'admin_enqueue_scripts', [ $admin_notice_controller, 'enqueue_scripts' ] );
add_action( 'wp_ajax_dismiss_vip_notice', [ $admin_notice_controller, 'dismiss_vip_notice' ] );
add_action( 'admin_init', [ $admin_notice_controller, 'maybe_clean_stale_dismissed_notices' ] );
add_action(
	'init',
	function() use ( $admin_notice_controller ) {
		do_action( 'vip_admin_notice_init', $admin_notice_controller );
	}
);

add_action(
	'vip_admin_notice_init',
	function( $admin_notice_controller ) {
		$admin_notice_controller->add(
			new Admin_Notice(
				'WordPress 5.9 is targeted for release on January 25, 2022. The latest Release Candidate (RC) is available now. Open a ticket to upgrade a non-prod environment to the latest version for testing.',
				[
					new Date_Condition( '2022-01-01', '2022-02-01' ),
					new WP_Version_Condition( '5.8', '5.9' ),
					new Capability_Condition( 'administrator' ),
				],
				'wp-5.9'
			)
		);
	}
);
