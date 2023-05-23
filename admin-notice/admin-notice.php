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
	function() use ( $admin_notice_controller ) {
		do_action( 'vip_admin_notice_init', $admin_notice_controller );
	}
);

// Old WP version w/o pinned Jetpack version
add_action(
	'vip_admin_notice_init',
	function( $admin_notice_controller ) {
		global $wp_version;
		$message = "We've noticed that you are running WordPress {$wp_version}, which is an outdated version. This prevents you from running the latest version of Jetpack, as the current version of Jetpack only supports 5.9 and up. Please upgrade to the most recent WordPress version to use the latest features of Jetpack.";

		$admin_notice_controller->add(
			new Admin_Notice(
				$message,
				[
					new Expression_Condition( version_compare( $wp_version, '5.9', '<' ) ),
					new Expression_Condition( ! defined( 'VIP_JETPACK_PINNED_VERSION' ) ),
				],
				'old-wp-versions',
				'error'
			)
		);
	}
);
