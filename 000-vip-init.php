<?php

/**
 * Initialisation for various VIP functionality
 *
 * By virtue of the filename, this file is included first of
 * all the files in the VIP Go MU plugins directory. All
 * VIP code should be initialised here, unless there's a
 * good reason not to.
 */

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

// For backwards compatibility - always true.
if ( ! defined( 'WPCOM_IS_VIP_ENV' ) ) {
	define( 'WPCOM_IS_VIP_ENV', false );
}

define( 'WPCOM_SANDBOXED', false !== strpos( gethostname(), '_web_dev_' ) );

// Used to verify emails sent via our SMTP servers
if ( ! defined( 'WPCOM_VIP_MAIL_TRACKING_KEY' ) ) {
	define( 'WPCOM_VIP_MAIL_TRACKING_KEY', false );
}

// Define constants for custom VIP Go paths
define( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR', WP_CONTENT_DIR . '/client-mu-plugins' );
define( 'WPCOM_VIP_PRIVATE_DIR', WPCOM_SANDBOXED ? '/chroot/private' : '/private' );

// Define these values just in case
defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ) or define( 'WPCOM_VIP_MACHINE_USER_LOGIN', 'vip' );
defined( 'WPCOM_VIP_MACHINE_USER_NAME' )  or define( 'WPCOM_VIP_MACHINE_USER_NAME', 'VIP' );
defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ) or define( 'WPCOM_VIP_MACHINE_USER_EMAIL', 'donotreply@wordpress.com' );
defined( 'WPCOM_VIP_MACHINE_USER_ROLE' )  or define( 'WPCOM_VIP_MACHINE_USER_ROLE', 'administrator' );

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
require_once( __DIR__ . '/vip-helpers/vip-caching.php' );
require_once( __DIR__ . '/vip-helpers/vip-roles.php' );
require_once( __DIR__ . '/vip-helpers/vip-permastructs.php' );
require_once( __DIR__ . '/vip-helpers/vip-mods.php' );
require_once( __DIR__ . '/vip-helpers/vip-media.php' );
require_once( __DIR__ . '/vip-helpers/vip-elasticsearch.php' );
require_once( __DIR__ . '/vip-helpers/vip-stats.php' );
require_once( __DIR__ . '/vip-helpers/vip-deprecated.php' );
require_once( __DIR__ . '/vip-helpers/vip-syndication-cache.php' );

//enabled on selected sites for now
if ( true === defined( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) && true === constant( 'WPCOM_VIP_CLEAN_TERM_CACHE' ) ) {
	require_once dirname( __FILE__ ) . '/vip-helpers/vip-clean-term-cache.php';
}

// Load WP_CLI helpers
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( __DIR__ . '/vip-helpers/vip-wp-cli.php' );
}

// Add Automattic's custom header
add_action( 'send_headers', function() {
	if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
		header( "X-hacker: If you're reading this, you should visit automattic.com/jobs and apply to join the fun, mention this header." );
	}
} );

do_action( 'vip_loaded' );
