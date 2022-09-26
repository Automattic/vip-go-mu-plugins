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

// PHP 8 migration nudge on non-prods
add_action(
	'vip_admin_notice_init',
	function( $admin_notice_controller ) {
		$message = 'All WordPress environments on the VIP Platform that are not running PHP 8.0 or above by Monday, the 15th of November 2022, <a href="https://lobby.vip.wordpress.com/2022/07/06/working-together-the-path-to-php-8-0/" target="_blank">will be updated by the WordPress VIP team</a>. Please upgrade your application to PHP 8.0 or 8.1 ahead of this date, to address any potential compatibility issues. Applications updated by the WordPress VIP team will not have a rollback option to a prior PHP version, and WordPress VIP will not be responsible for any issues the update may cause to your site(s). <a href="https://wpvip.com/2022/07/06/how-to-prepare-your-wordpress-site-for-php-8/" target="_blank">Visit our PHP 8 Update Guide to get started.</a>';
		$admin_notice_controller->add(
			new Admin_Notice(
				$message,
				[
					new Expression_Condition( is_super_admin() ),
					new Expression_Condition( version_compare( PHP_VERSION, '8.0', '<' ) ),
				],
				'php8-migrations-3',
				'info'
			)
		);
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
					new Expression_Condition( is_super_admin() ),
					new Expression_Condition( version_compare( $wp_version, '5.9', '<' ) ),
					new Expression_Condition( ! defined( 'VIP_JETPACK_PINNED_VERSION' ) ),
				],
				'old-wp-versions',
				'error'
			)
		);
	}
);
