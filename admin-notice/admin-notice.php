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
		if ( defined( 'SEARCH_MIGRATIONS_EXTENDED_IDS' ) && in_array( FILES_CLIENT_SITE_ID, SEARCH_MIGRATIONS_EXTENDED_IDS, true ) ) {
			$time = 'in the near future';
		} else {
			$time = 'as of May 4, 2022';
		}

		$message = 'Howdy! We have detected the JETPACK_SEARCH_VIP_INDEX constant is still defined on this application. As a friendly reminder, <a href="https://lobby.vip.wordpress.com/2022/02/02/enterprise-search-as-default-elasticsearch-solution/" target="_blank" title="Enterprise Search as default Elasticsearch solution">Jetpack Search custom indexes will no longer be supported on VIP ' . $time . '</a>. Please use <a href="https://docs.wpvip.com/how-tos/vip-search/enable/#jetpack-migration-support-path" target="_blank" title="Jetpack migration support path">Enterprise Search</a> or Jetpack Instant Search instead.';

		$admin_notice_controller->add(
			new Admin_Notice(
				$message,
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

// PHP 8 migration nudge on non-prods
add_action(
	'vip_admin_notice_init',
	function( $admin_notice_controller ) {
		$message = 'PHP 7.4 will <a href="https://href.li/?https://www.php.net/supported-versions.php" target="_blank"> stop receiving security updates</a> on November 28, 2022. On this date, all sites on WordPress VIP still using PHP 7.4 will be upgraded to PHP 8.0 to minimize the risk from unpatched security vulnerabilities. To learn more, please see the <a href="https://lobby.vip.wordpress.com/2022/05/02/php-8-0-available-on-wordpress-vip/" target="_blank">Lobby announcement</a> and the guide on <a href="https://docs.wpvip.com/how-tos/code-scanning-for-php-upgrade/">how to prepare your application for a PHP version upgrade</a>.';

		$admin_notice_controller->add(
			new Admin_Notice(
				$message,
				[
					new Expression_Condition( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== VIP_GO_APP_ENVIRONMENT ),
					new Expression_Condition( version_compare( PHP_VERSION, '8.0', '<' ) ),
				],
				'php8-migrations',
				'info'
			)
		);
	}
);
