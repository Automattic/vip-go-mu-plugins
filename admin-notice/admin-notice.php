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

$admin_notice_controller = new Admin_Notice_Controller();

add_action( 'admin_notices', [ $admin_notice_controller, 'display_notices' ] );
add_action( 'admin_enqueue_scripts', [ $admin_notice_controller, 'enqueue_scripts' ] );
add_action( 'wp_ajax_dismiss_vip_notice', [ $admin_notice_controller, 'dismiss_vip_notice' ] );
add_action( 'admin_init', [ $admin_notice_controller, 'maybe_clean_stale_dismissed_notices' ] );
add_action(
	'init',
	function () use ( $admin_notice_controller ) {
		do_action( 'vip_admin_notice_init', $admin_notice_controller );
	}
);

add_action(
	'vip_admin_notice_init',
	function ( $admin_notice_controller ) {
		$message = 'Heads up! Your site is using a deprecated plugin <a href="https://github.com/Automattic/jetpack-force-2fa/">jetpack-force-2fa</a>. This functionality is already included in Jetpack as of version 13.5. Please remove the plugin to avoid potential conflicts with future Jetpack updates.';
		$admin_notice_controller->add(
			new Admin_Notice(
				$message,
				[
					new Expression_Condition( class_exists( 'Jetpack_Force_2FA' ) && class_exists( 'Jetpack' ) && defined( 'JETPACK__VERSION' ) && version_compare( JETPACK__VERSION, '13.5', '>=' ) ),
				],
				'deprecated-standalone-jetpack-2fa-plugin',
				'error'
			)
		);
	}
);
