<?php

/**
 * Plugin Name: VIP Init
 * Description: Initializes critical elements of the VIP environment.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * Remember vip-init.php? This is like that, but better!
 */

use Automattic\VIP\Config\Sync;
use Automattic\VIP\Utils\Context;
use Automattic\VIP\Utils\WPComVIP_Restrictions;

use function Automattic\VIP\Core\Constants\define_db_constants;

// @codeCoverageIgnoreStart

/**
 * By virtue of the filename, this file is included first of
 * all the files in the VIP Go MU plugins directory. All
 * VIP code should be initialised here, unless there's a
 * good reason not to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Important - Cache-healthcheck and App-healthcheck
require_once __DIR__ . '/healthcheck/healthcheck.php';

if ( ! defined( 'WPVIP_MU_PLUGIN_DIR' ) ) {
	define( 'WPVIP_MU_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'WPCOM_VIP_SITE_MAINTENANCE_MODE' ) ) {
	define( 'WPCOM_VIP_SITE_MAINTENANCE_MODE', false );
}

if ( ! defined( 'VIP_OVERDUE_LOCKOUT' ) ) {
	define( 'VIP_OVERDUE_LOCKOUT', false );
}

if ( ! defined( 'WPCOM_VIP_SITE_ADMIN_ONLY_MAINTENANCE' ) ) {
	define( 'WPCOM_VIP_SITE_ADMIN_ONLY_MAINTENANCE', false );
}

if ( ! class_exists( Context::class ) ) {
	require_once __DIR__ . '/lib/utils/class-context.php';
}

// Sites can be blocked for various reasons - usually maintenance, so exit
// early if the constant has been set (defined by VIP Go in config/wp-config.php)
if ( WPCOM_VIP_SITE_MAINTENANCE_MODE ) {
	$allow_front_end = WPCOM_VIP_SITE_ADMIN_ONLY_MAINTENANCE && ! REST_REQUEST && ! WP_ADMIN;

	// WP CLI is allowed, but disable cron
	if ( Context::is_wp_cli() || $allow_front_end ) {
		add_filter( 'pre_option_a8c_cron_control_disable_run', function () {
			return 1;
		}, 9999 );
	} else {
		// Don't try to short-circuit Jetpack requests, otherwise it will break the connection.
		require_once __DIR__ . '/vip-helpers/vip-utils.php';
		if ( ! vip_is_jetpack_request() ) {
			http_response_code( 503 );

			header( 'X-VIP-Go-Maintenance: true' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo file_get_contents( __DIR__ . '/errors/site-maintenance.html' );

			exit;
		}
	}
}

// Sites can be disabled if there is an overdue payment.
// This constant is defined by VIP Go in config/wp-config.php.
// WP CLI (and cron) is allowed
if ( Context::is_vip_env() && Context::is_overdue_locked() && ! Context::is_wp_cli() ) {
	// Don't try to short-circuit Jetpack requests, otherwise it will break the connection.
	require_once __DIR__ . '/vip-helpers/vip-utils.php';
	if ( ! vip_is_jetpack_request() ) {
		http_response_code( 402 );

		header( 'X-VIP-402: true' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo file_get_contents( __DIR__ . '/errors/site-shutdown.html' );

		exit;
	}
}

if ( file_exists( __DIR__ . '/.secrets/vip-secrets.php' ) ) {
	require __DIR__ . '/.secrets/vip-secrets.php';
}

require_once __DIR__ . '/lib/environment/class-environment.php';

// phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.UsingRestrictedConstant -- by design
if ( ! defined( 'A8C_PROXIED_REQUEST' ) ) {
	/**
	 * @var constant A8C_PROXIED_REQUEST Set to true if the current request is made via the Automattic proxy, which is only available to Automatticians.
	 */
	define( 'A8C_PROXIED_REQUEST', false ); // phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.DefiningRestrictedConstant
}

if ( ! defined( 'VIP_GO_ENV' ) ) {
	/**
	 * @constant VIP_GO_ENV The name of the current VIP Go environment. Falls back to `false`.
	 */
	define( 'VIP_GO_ENV', false );
}

// On VIP Go environments this will already be set to true in wp-config.php
// Default to false for other environments, e.g. local development
if ( ! defined( 'WPCOM_IS_VIP_ENV' ) ) {
	define( 'WPCOM_IS_VIP_ENV', false );
}

define( 'WPCOM_SANDBOXED', \Automattic\VIP\Environment::is_sandbox_container( gethostname(), getenv() ) );
define( 'VIP_GO_IS_CLI_CONTAINER', \Automattic\VIP\Environment::is_batch_container( gethostname(), getenv() ) );

// Used to verify emails sent via our SMTP servers
if ( ! defined( 'WPCOM_VIP_MAIL_TRACKING_KEY' ) ) {
	define( 'WPCOM_VIP_MAIL_TRACKING_KEY', false );
}

// Define constants for custom VIP Go paths
if ( ! defined( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR' ) ) {
	define( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR', WP_CONTENT_DIR . '/client-mu-plugins' );
}

if ( method_exists( Context::class, 'is_fedramp' ) && Context::is_fedramp() ) {
	// FedRAMP sites do not load Jetpack by default
	if ( ! defined( 'VIP_JETPACK_SKIP_LOAD' ) ) {
		define( 'VIP_JETPACK_SKIP_LOAD', true );
	}
}

$private_dir_path = WP_CONTENT_DIR . '/private'; // Local fallback
if ( false !== VIP_GO_ENV ) {
	if ( is_dir( '/private' ) ) {
		$private_dir_path = '/private';
	} elseif ( is_dir( '/chroot/private' ) ) {
		$private_dir_path = '/chroot/private';
	}
}
define( 'WPCOM_VIP_PRIVATE_DIR', $private_dir_path );
unset( $private_dir_path );

// Define these values just in case
defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ) || define( 'WPCOM_VIP_MACHINE_USER_LOGIN', 'vip' );
defined( 'WPCOM_VIP_MACHINE_USER_NAME' ) || define( 'WPCOM_VIP_MACHINE_USER_NAME', 'VIP' );
defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ) || define( 'WPCOM_VIP_MACHINE_USER_EMAIL', 'donotreply@wordpress.com' );
defined( 'WPCOM_VIP_MACHINE_USER_ROLE' ) || define( 'WPCOM_VIP_MACHINE_USER_ROLE', 'administrator' );

if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
	add_action( 'set_current_user', function () {
		$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );

		if ( $user && $user->ID ) {
			defined( 'WPCOM_VIP_MACHINE_USER_ID' ) || define( 'WPCOM_VIP_MACHINE_USER_ID', $user->ID );
		}
	}, PHP_INT_MIN );
}

// Support a limited number of additional "Internal Events" in Cron Control.
// These events run regardless of the number of pending events, and they cannot be deleted.
$internal_cron_events = array(
	array(
		'schedule' => 'hourly',
		'action'   => 'wpcom_vip_support_remove_user_via_cron', // Equals to the value of Automattic\VIP\Support_User\User::CRON_ACTION
		'callback' => array( 'Automattic\VIP\Support_User\User', 'do_cron_cleanup' ),
	),
);

define( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS', $internal_cron_events );

// Enable Jetpack private connection by default on non production sites
if ( ! defined( 'VIP_JETPACK_IS_PRIVATE' ) && defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== VIP_GO_APP_ENVIRONMENT ) {
	define( 'VIP_JETPACK_IS_PRIVATE', true );
}

// Jetpack Connection Pilot is enabled by default on VIP Go environments
if ( ! defined( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION' ) ) {
	define( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION', WPCOM_IS_VIP_ENV );
}

if ( ! defined( 'FS_METHOD' ) ) {
	// Interaction with the filesystem will always be direct.
	// Avoids issues with `get_filesystem_method` which attempts to write to `WP_CONTENT_DIR` and fails.
	define( 'FS_METHOD', 'direct' );
}

if ( WPCOM_SANDBOXED ) {
	require __DIR__ . '/vip-helpers/sandbox.php';
}

// Feature flags
require_once __DIR__ . '/lib/feature/class-feature.php';

// Stats collection
require_once __DIR__ . '/prometheus.php';

// Logging
require_once __DIR__ . '/logstash/logstash.php';
require_once __DIR__ . '/lib/statsd/class-statsd.php';

// Debugging Tools
require_once __DIR__ . '/000-debug/0-load.php';
require_once __DIR__ . '/lib/utils/class-alerts.php';

// Polyfills
require_once __DIR__ . '/lib/helpers/php-compat.php';

// Load our development and environment helpers
require_once __DIR__ . '/vip-helpers/vip-notoptions-mitigation.php';
require_once __DIR__ . '/vip-helpers/vip-utils.php';
require_once __DIR__ . '/vip-helpers/vip-newrelic.php';
require_once __DIR__ . '/vip-helpers/vip-caching.php';
require_once __DIR__ . '/vip-helpers/vip-roles.php';
require_once __DIR__ . '/vip-helpers/vip-permastructs.php';
require_once __DIR__ . '/vip-helpers/vip-mods.php';
require_once __DIR__ . '/vip-helpers/vip-media.php';
require_once __DIR__ . '/vip-helpers/vip-elasticsearch.php';
require_once __DIR__ . '/vip-helpers/vip-stats.php';
require_once __DIR__ . '/vip-helpers/vip-deprecated.php';
require_once __DIR__ . '/vip-helpers/vip-syndication-cache.php';
require_once __DIR__ . '/vip-helpers/vip-migrations.php';
require_once __DIR__ . '/vip-helpers/class-user-cleanup.php';
require_once __DIR__ . '/vip-helpers/class-wpcomvip-restrictions.php';

// Load the Telemetry files
require_once __DIR__ . '/telemetry/class-telemetry-system.php';
require_once __DIR__ . '/telemetry/class-tracks.php';
require_once __DIR__ . '/telemetry/class-telemetry-client.php';
require_once __DIR__ . '/telemetry/class-telemetry-event-queue.php';
require_once __DIR__ . '/telemetry/class-telemetry-event.php';
require_once __DIR__ . '/telemetry/tracks/class-tracks-event-dto.php';
require_once __DIR__ . '/telemetry/tracks/class-tracks-event.php';
require_once __DIR__ . '/telemetry/tracks/class-tracks-client.php';
require_once __DIR__ . '/telemetry/tracks/tracks-utils.php';

add_action( 'init', [ WPComVIP_Restrictions::class, 'instance' ] );

//enabled on selected sites for now
if ( true === defined( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) && true === constant( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) ) {
	require_once __DIR__ . '/vip-helpers/vip-clean-term-cache.php';
}

// Load WP_CLI helpers
if ( Context::is_wp_cli() ) {
	require_once __DIR__ . '/vip-helpers/vip-wp-cli.php';
	require_once __DIR__ . '/vip-helpers/class-vip-backup-user-role-cli.php';
}

// Load elasticsearch helpers
// Warning: Site Details depends on the existence of class Search.
// If this changes in the future, please ensure that details for search are correctly extracted
if ( ( defined( 'USE_VIP_ELASTICSEARCH' ) && USE_VIP_ELASTICSEARCH ) || // legacy constant name
	( defined( 'VIP_ENABLE_VIP_SEARCH' ) && true === VIP_ENABLE_VIP_SEARCH ) ) {
	require_once __DIR__ . '/search/search.php';
	if ( ! defined( 'VIP_SEARCH_ENABLED_BY' ) ) {
		define( 'VIP_SEARCH_ENABLED_BY', 'constant' );
	}
}

// Set WordPress environment type
// Map some VIP environments to 'production' and 'development', and use 'staging' for any other
if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	switch ( VIP_GO_APP_ENVIRONMENT ) {
		case 'production':
			$environment_type = 'production';
			break;
		case 'develop':
		case 'development':
			$environment_type = 'development';
			break;
		case 'local':
			$environment_type = 'local';
			break;
		default:
			$environment_type = 'staging';
			break;
	}

	define( 'WP_ENVIRONMENT_TYPE', $environment_type );
}

$non_prod_envs = [
	'local',
	'develop',
	'preprod',
	'staging',
	'testing',
	'uat',
	'development',
	'dev',
	'stage',
];
if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && in_array( constant( 'VIP_GO_APP_ENVIRONMENT' ), $non_prod_envs, true ) && file_exists( __DIR__ . '/vip-helpers/vip-non-production.php' ) ) {
	require __DIR__ . '/vip-helpers/vip-non-production.php';
}

if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
	// Load config related helpers
	require_once __DIR__ . '/config/class-sync.php';

	add_action( 'init', [ Sync::class, 'instance' ] );


	// Load _encloseme meta cleanup scheduler
	require_once __DIR__ . '/lib/class-vip-encloseme-cleanup.php';

	$encloseme_cleaner = new VIP_Encloseme_Cleanup();
	$encloseme_cleaner->init();
}

// Add custom header for VIP
add_filter( 'wp_headers', function ( $headers ) {
	$headers['X-hacker']     = 'If you\'re reading this, you should visit wpvip.com/careers and apply to join the fun, mention this header.';
	$headers['X-Powered-By'] = 'WordPress VIP <https://wpvip.com>';
	$headers['Host-Header']  = 'a9130478a60e5f9135f765b23f26593b'; // md5 -s wpvip

	// Non-production applications and go-vip.(co|net) domains should not be indexed.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- should be safe, because we are only looking for a substring and don't use the variable for anything else
	if ( 'production' !== VIP_GO_ENV || is_vip_convenience_domain( $_SERVER['HTTP_HOST'] ?? '' ) ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow';
	}

	return $headers;
} );

if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
	// Disable core sitemaps
	//
	// https://make.wordpress.org/core/2020/07/22/new-xml-sitemaps-functionality-in-wordpress-5-5/
	add_filter( 'wp_sitemaps_enabled', '__return_false' );
}

if ( file_exists( __DIR__ . '/001-core/constants.php' ) ) {
	require_once __DIR__ . '/001-core/constants.php'; // Define the DB constants
}

if ( function_exists( '\Automattic\VIP\Core\Constants\define_db_constants' ) ) {
	define_db_constants( $GLOBALS['wpdb'] );
}

if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
	$_SERVER['HTTP_HOST'] = null;
}

do_action( 'vip_loaded' );
// @codeCoverageIgnoreEnd
