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

add_action(
	'vip_admin_notice_init',
	function( $admin_notice_controller ) {
		$admin_notice_controller->add(
			new Admin_Notice(
				'Jetpack Search custom indexes are targeted for end-of-life on May 4, 2022. <a href="https://lobby.vip.wordpress.com/2022/02/02/enterprise-search-as-default-elasticsearch-solution/" target="_blank" title="Enterprise Search as default Elasticsearch solution">Please use Enterprise Search or Jetpack Instant Search instead.</a>',
				[
					new Date_Condition( '2022-01-01', '2022-05-04' ),
					new Expression_Condition( defined( 'JETPACK_SEARCH_VIP_INDEX' ) && JETPACK_SEARCH_VIP_INDEX ),
					new Capability_Condition( 'administrator' ),
				],
				'search-migrations',
				'error'
			)
		);
	}
);
