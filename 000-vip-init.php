<?php

/**
 * Plugin Name: VIP Init
 * Description: Initializes critical elements of the VIP environment.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * Remember vip-init.php? This is like that, but better!
 */

/**
 * By virtue of the filename, this file is included first of
 * all the files in the VIP Go MU plugins directory. All
 * VIP code should be initialised here, unless there's a
 * good reason not to.
 */

// Execute the healthcheck as quickly as possible
if ( '/cache-healthcheck?' === $_SERVER['REQUEST_URI'] ) {
	if ( function_exists( 'newrelic_end_transaction' ) ) {
		// Discard the transaction (the `true` param)
		// See: https://docs.newrelic.com/docs/agents/php-agent/configuration/php-agent-api#api-end-txn
		newrelic_end_transaction( true );
	}

	http_response_code( 200 );

	die( 'ok' );
}

if ( ! defined( 'WPCOM_VIP_SITE_MAINTENANCE_MODE' ) ) {
	define( 'WPCOM_VIP_SITE_MAINTENANCE_MODE', false );
}

if ( ! defined( 'WPCOM_VIP_SITE_ADMIN_ONLY_MAINTENANCE' ) ) {
	define( 'WPCOM_VIP_SITE_ADMIN_ONLY_MAINTENANCE', false );
}

// Sites can be blocked for various reasons - usually maintenance, so exit
// early if the constant has been set (defined by VIP Go in config/wp-config.php)
if ( WPCOM_VIP_SITE_MAINTENANCE_MODE ) {
	$allow_front_end = WPCOM_VIP_SITE_ADMIN_ONLY_MAINTENANCE && ! REST_REQUEST && ! WP_ADMIN;

	// WP CLI is allowed, but disable cron
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || $allow_front_end ) {
		add_filter( 'pre_option_a8c_cron_control_disable_run', function() {
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

if ( file_exists( __DIR__ . '/.secrets/vip-secrets.php' ) ) {
	require __DIR__ . '/.secrets/vip-secrets.php';
}

require_once __DIR__ . '/lib/environment/class-environment.php';

if ( ! defined( 'A8C_PROXIED_REQUEST' ) ) {
	/**
	 * @var constant A8C_PROXIED_REQUEST Set to true if the current request is made via the Automattic proxy, which is only available to Automatticians.
	 */
	define( 'A8C_PROXIED_REQUEST', false );
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

define( 'WPCOM_SANDBOXED', \Automattic\VIP\Environment::is_sandbox_container( gethostname(), $_ENV ) );
define( 'VIP_GO_IS_CLI_CONTAINER', \Automattic\VIP\Environment::is_batch_container( gethostname(), $_ENV ) );

// Used to verify emails sent via our SMTP servers
if ( ! defined( 'WPCOM_VIP_MAIL_TRACKING_KEY' ) ) {
	define( 'WPCOM_VIP_MAIL_TRACKING_KEY', false );
}

// Define constants for custom VIP Go paths
define( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR', WP_CONTENT_DIR . '/client-mu-plugins' );

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
defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ) or define( 'WPCOM_VIP_MACHINE_USER_LOGIN', 'vip' );
defined( 'WPCOM_VIP_MACHINE_USER_NAME' )  or define( 'WPCOM_VIP_MACHINE_USER_NAME', 'VIP' );
defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ) or define( 'WPCOM_VIP_MACHINE_USER_EMAIL', 'donotreply@wordpress.com' );
defined( 'WPCOM_VIP_MACHINE_USER_ROLE' )  or define( 'WPCOM_VIP_MACHINE_USER_ROLE', 'administrator' );

add_action( 'set_current_user', function() {
	$user = get_user_by( 'login', WPCOM_VIP_MACHINE_USER_LOGIN );

	if ( $user && $user->ID ) {
		defined( 'WPCOM_VIP_MACHINE_USER_ID' ) or define( 'WPCOM_VIP_MACHINE_USER_ID', $user->ID );
	}
}, PHP_INT_MIN );

// Support a limited number of additional "Internal Events" in Cron Control.
// These events run regardless of the number of pending events, and they cannot be deleted.
$internal_cron_events = array(
	array(
		'schedule' => 'hourly',
		'action'   => 'wpcom_vip_support_remove_user_via_cron', // Automattic\VIP\Support_User\User::CRON_ACTION
		'callback' => array( 'Automattic\VIP\Support_User\User', 'do_cron_cleanup' ),
	)
);

// Enable Jetpack private connection by default on non production sites
if ( ! defined( 'VIP_JETPACK_IS_PRIVATE' ) && defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' !== VIP_GO_APP_ENVIRONMENT ) {
	define( 'VIP_JETPACK_IS_PRIVATE', true );
}

// Jetpack Connection Pilot is enabled by default
if ( ! defined( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION' ) ) {
	// Keeping for historical reasons, we can remove this after clients are using the new constant
	if ( defined( 'VIP_JETPACK_CONNECTION_PILOT_SHOULD_RUN' ) ) {
		define( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION', VIP_JETPACK_CONNECTION_PILOT_SHOULD_RUN );
	} else {
		define( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION', true );
	}
}

if ( defined( 'VIP_JETPACK_AUTO_MANAGE_CONNECTION' ) && true === VIP_JETPACK_AUTO_MANAGE_CONNECTION ) {
	$internal_cron_events[] = array(
		'schedule'  => 'hourly',
		'action'    => 'wpcom_vip_run_jetpack_connection_pilot',
		'callback'  => array( '\Automattic\VIP\Jetpack\Connection_Pilot', 'do_cron' ),
	);
}

define( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS', $internal_cron_events );

if ( ! defined( 'VIP_SEARCH_DEV_TOOLS' ) ) {
	define( 'VIP_SEARCH_DEV_TOOLS', 'production' !== VIP_GO_APP_ENVIRONMENT || \Automattic\VIP\Feature::is_enabled( 'search-dev-tools' ) );
}

// Interaction with the filesystem will always be direct.
// Avoids issues with `get_filesystem_method` which attempts to write to `WP_CONTENT_DIR` and fails.
define( 'FS_METHOD', 'direct' );

if ( WPCOM_SANDBOXED ) {
	require __DIR__ . '/vip-helpers/sandbox.php';
}

// Feature flags
require_once( __DIR__ . '/lib/feature/class-feature.php' );

// Logging
require_once( __DIR__ . '/logstash/logstash.php' );
require_once( __DIR__ . '/lib/statsd/class-statsd.php' );

// Debugging Tools
require_once( __DIR__ . '/000-debug/0-load.php' );
require_once( __DIR__ . '/lib/utils/class-alerts.php' );

// Load our development and environment helpers
require_once( __DIR__ . '/vip-helpers/vip-notoptions-mitigation.php' );
require_once( __DIR__ . '/vip-helpers/vip-utils.php' );
require_once( __DIR__ . '/vip-helpers/vip-newrelic.php' );
require_once( __DIR__ . '/vip-helpers/vip-caching.php' );
require_once( __DIR__ . '/vip-helpers/vip-roles.php' );
require_once( __DIR__ . '/vip-helpers/vip-permastructs.php' );
require_once( __DIR__ . '/vip-helpers/vip-mods.php' );
require_once( __DIR__ . '/vip-helpers/vip-media.php' );
require_once( __DIR__ . '/vip-helpers/vip-elasticsearch.php' );
require_once( __DIR__ . '/vip-helpers/vip-stats.php' );
require_once( __DIR__ . '/vip-helpers/vip-deprecated.php' );
require_once( __DIR__ . '/vip-helpers/vip-syndication-cache.php' );
require_once( __DIR__ . '/vip-helpers/vip-migrations.php' );

//enabled on selected sites for now
if ( true === defined( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) && true === constant( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) ) {
	require_once dirname( __FILE__ ) . '/vip-helpers/vip-clean-term-cache.php';
}

// Load WP_CLI helpers
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( __DIR__ . '/vip-helpers/vip-wp-cli.php' );
	require_once( __DIR__ . '/vip-helpers/class-vip-backup-user-role-cli.php' );
}

// Load elasticsearch helpers
// Warning: Site Details depends on the existence of class Search.
// If this changes in the future, please ensure that details for search are correctly extracted
if ( ( defined( 'USE_VIP_ELASTICSEARCH' ) && USE_VIP_ELASTICSEARCH ) || // legacy constant name
	defined( 'VIP_ENABLE_VIP_SEARCH' ) && true === VIP_ENABLE_VIP_SEARCH ) {
	require_once( __DIR__ . '/search/search.php' );
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
		default:
			$environment_type = 'staging';
			break;
	}

	define( 'WP_ENVIRONMENT_TYPE', $environment_type );

	// VIP sites should not be set as staging in Jetpack
	// since it breaks SSO and prevents data from being passed to
	// WordPress.com
	add_filter( 'jetpack_is_staging_site', '__return_false' );
}

// Load config related helpers
require_once( __DIR__ . '/config/class-sync.php' );

add_action( 'init', function() {
	\Automattic\VIP\Config\Sync::instance();
} );

// Load _encloseme meta cleanup scheduler
require_once( __DIR__ . '/lib/class-vip-encloseme-cleanup.php' );

$encloseme_cleaner = new VIP_Encloseme_Cleanup();
$encloseme_cleaner->init();

// Add custom header for VIP
add_filter( 'wp_headers', function( $headers ) {
	$headers['X-hacker'] = 'If you\'re reading this, you should visit wpvip.com/careers and apply to join the fun, mention this header.';
	$headers['X-Powered-By'] = 'WordPress VIP <https://wpvip.com>';
	$headers['Host-Header'] = 'a9130478a60e5f9135f765b23f26593b'; // md5 -s wpvip

	// Non-production applications and go-vip.(co|net) domains should not be indexed.
	if ( 'production' !== VIP_GO_ENV || false !== strpos( $_SERVER[ 'HTTP_HOST' ], '.go-vip.' ) ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow';
	}

	return $headers;
} );

// Disable core sitemaps
//
// https://make.wordpress.org/core/2020/07/22/new-xml-sitemaps-functionality-in-wordpress-5-5/
add_filter( 'wp_sitemaps_enabled', '__return_false' );

do_action( 'vip_loaded' );
