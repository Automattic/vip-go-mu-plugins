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

// Sites can be blocked for various reasons - usually maintenance, so exit
// early if the constant has been set (defined by VIP Go in config/wp-config.php)
if ( defined( 'WPCOM_VIP_SITE_MAINTENANCE_MODE' ) && WPCOM_VIP_SITE_MAINTENANCE_MODE ) {
	// WP CLI is allowed, but disable cron
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		add_filter( 'pre_option_a8c_cron_control_disable_run', function() {
			return 1;
		}, 9999 );
	} else {
		http_response_code( 503 );

		header( 'X-VIP-Go-Maintenance: true' );

		echo file_get_contents( __DIR__ . '/errors/site-maintenance.html' );

		exit;
	}
}

if ( file_exists( __DIR__ . '/.secrets/vip-secrets.php' ) ) {
	require __DIR__ . '/.secrets/vip-secrets.php';
}

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

define( 'WPCOM_SANDBOXED', false !== strpos( gethostname(), '_web_dev_' ) );
define( 'VIP_GO_IS_CLI_CONTAINER', false !== strpos( gethostname(), '_wpcli_' ) );

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

// Support a limited number of additional "Internal Events" in Cron Control.
// These events run regardless of the number of pending events, and they cannot be deleted.
define( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS', array(
	array(
		'schedule' => 'hourly',
		'action'   => 'wpcom_vip_support_remove_user_via_cron', // Automattic\VIP\Support_User\User::CRON_ACTION
		'callback' => array( 'Automattic\VIP\Support_User\User', 'do_cron_cleanup' ),
	),
) );

// Interaction with the filesystem will always be direct.
// Avoids issues with `get_filesystem_method` which attempts to write to `WP_CONTENT_DIR` and fails.
define( 'FS_METHOD', 'direct' );

if ( WPCOM_SANDBOXED ) {
	require __DIR__ . '/vip-helpers/sandbox.php';
}

// Debugging Tools
require_once( __DIR__ . '/000-debug/0-load.php' );

// Load our development and environment helpers
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
}

// Add custom header for VIP
add_filter( 'wp_headers', function( $headers ) {
	$headers['X-hacker'] = 'If you\'re reading this, you should visit wpvip.com/careers and apply to join the fun, mention this header.';
	$headers['X-Powered-By'] = 'WordPress.com VIP <https://wpvip.com>';

	// All non-production domains should not be indexed.
	// This should not apply only to *.vip-go.co
	if ( 'production' !== VIP_GO_ENV ) {
		$headers['X-Robots-Tag'] = 'noindex, nofollow';
	}

	return $headers;
} );

do_action( 'vip_loaded' );
