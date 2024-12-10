<?php

/*
 * Plugin Name: Jetpack: VIP Specific Changes
 * Plugin URI: https://github.com/Automattic/vipv2-mu-plugins/blob/master/jetpack-mandatory.php
 * Description: VIP-specific customisations to Jetpack.
 * Author: Automattic
 * Version: 1.0.2
 * License: GPL2+
 */

/**
 * Lowest incremental sync queue size allowed on VIP - JP default is 1000, but we're bumping to 10000 to give VIPs more
 * headroom as they tend to publish more than average
 */
define( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_LOWER_LIMIT', 10000 );

/**
 * The largest incremental sync queue size allowed - items will not get enqueued if there are already this many pending
 *
 * The queue is stored in the option table, so if the queue gets _too_ large, site performance suffers
 */
define( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_UPPER_LIMIT', 100000 );

/**
 * The lower bound for the incremental sync queue lag - if the oldest item has been sitting unsynced for this long,
 * new items will not be added to the queue
 *
 * The default is 15 minutes, but VIP sites often have more busy queues and we must prevent dropping items if the sync is
 * running behind
 */
define( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_LOWER_LIMIT', 2 * HOUR_IN_SECONDS );

/**
 * The maximum incremental sync queue lag allowed - just sets a reasonable upper bound on this limit to prevent extremely
 * stale incremental sync queues
 */
define( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_UPPER_LIMIT', DAY_IN_SECONDS );

/**
 * Enable the the new Jetpack full sync method (queue-less) on non-production sites for testing
 *
 * Can be removed (along with later code that uses the constant) after Jetpack 8.2 is deployed
 */
if ( ! defined( 'VIP_JETPACK_FULL_SYNC_IMMEDIATELY' ) && 'production' !== VIP_GO_ENV ) {
	define( 'VIP_JETPACK_FULL_SYNC_IMMEDIATELY', true );
}

/**
 * Add the Connection Pilot. Ensures Jetpack is consistently connected.
 */
require_once __DIR__ . '/connection-pilot/class-jetpack-connection-pilot.php';

/**
 * Enable VIP modules required as part of the platform
 */
require_once __DIR__ . '/jetpack-mandatory.php';

/**
 * Remove certain modules from the list of those that can be activated
 * Blocks access to certain functionality that isn't compatible with the platform.
 */
add_filter( 'jetpack_get_available_modules', function ( $modules ) {
	// The Photon service is not necessary on VIP Go since the same features are built-in.
	// Note that we do utilize some of the Photon module's code with our own Files Service.
	unset( $modules['photon'] );
	unset( $modules['photon-cdn'] );

	unset( $modules['site-icon'] );
	unset( $modules['protect'] );

	return $modules;
}, 999 );

/**
 * Lock down the jetpack_sync_settings_max_queue_size to an allowed range
 *
 * Still allows changing the value per site, but locks it into the range
 */
add_filter( 'option_jetpack_sync_settings_max_queue_size', function ( $value ) {
	$value = intval( $value );

	$value = min( $value, VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_UPPER_LIMIT );
	$value = max( $value, VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_LOWER_LIMIT );

	return $value;
}, 9999 );

/**
 * Lock down the jetpack_sync_settings_max_queue_lag to an allowed range
 *
 * Still allows changing the value per site, but locks it into the range
 */
add_filter( 'option_jetpack_sync_settings_max_queue_lag', function ( $value ) {
	$value = intval( $value );

	$value = min( $value, VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_UPPER_LIMIT );
	$value = max( $value, VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_LOWER_LIMIT );

	return $value;
}, 9999 );

/**
 * Allow incremental syncing via cron to take longer than the default 30 seconds.
 *
 * This will allow more items to be processed per cron event, while leaving a small buffer between completion and the start of the next event (the event interval is 5 mins).
 *
 */
add_filter( 'option_jetpack_sync_settings_cron_sync_time_limit', function () {
	return 4 * MINUTE_IN_SECONDS;
}, 9999 );

/**
 * Reduce the time between sync batches on VIP for performance gains.
 *
 * By default, this is 10 seconds, but VIP can be more aggressive and doesn't need to wait as long (we'll still wait a small amount).
 *
 */
add_filter( 'option_jetpack_sync_settings_sync_wait_time', function () {
	return 1;
}, 9999 );

// Prevent Jetpack version ping-pong when a sandbox has an old version of stacks
if ( true === WPCOM_SANDBOXED ) {
	add_action( 'updating_jetpack_version', function ( $new_version, $old_version ) {
		// This is a brand new site with no Jetpack data
		if ( empty( $old_version ) ) {
			return;
		}

		// If we're upgrading, then it's fine. We only want to prevent accidental downgrades
		// Jetpack::maybe_set_version_option() already does this check, but other spots
		// in JP can trigger this, without the check
		if ( version_compare( $new_version, $old_version, '>' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- version number is OK
		wp_die( sprintf( '😱😱😱 Oh no! Looks like your sandbox is trying to change the version of Jetpack (from %1$s => %2$s). This is probably not a good idea. As a precaution, we\'re killing this request to prevent potentially bad things. Please run `vip stacks update` on your sandbox before doing anything else.', $old_version, $new_version ), 400 );
	}, 0, 2 ); // No need to wait till priority 10 since we're going to die anyway
}

function wpcom_vip_did_jetpack_search_query( $query ) {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return;
	}

	global $wp_elasticsearch_queries_log;

	if ( ! isset( $wp_elasticsearch_queries_log ) || ! is_array( $wp_elasticsearch_queries_log ) ) {
		$wp_elasticsearch_queries_log = array();
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
	$query['backtrace'] = wp_debug_backtrace_summary();

	$wp_elasticsearch_queries_log[] = $query;
}

add_action( 'did_jetpack_search_query', 'wpcom_vip_did_jetpack_search_query' );

/**
 * Decide when Jetpack's Sync Listener should be loaded.
 *
 * Sync Listener looks for events that need to be added to the sync queue. On
 * many requests, such as frontend views, we wouldn't expect there to be any DB
 * writes so there should be nothing for Jetpack to listen for.
 *
 * @param  bool $should_load Current value.
 * @return bool              Whether (true) or not (false) Listener should load.
 */
function wpcom_vip_disable_jetpack_sync_for_frontend_get_requests( $should_load ) {
	// Don't run listener for frontend, non-cron GET requests

	if ( is_admin() ) {
		return $should_load;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return $should_load;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return $should_load;
	}

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
		$should_load = false;
	}

	return $should_load;
}
add_filter( 'jetpack_sync_listener_should_load', 'wpcom_vip_disable_jetpack_sync_for_frontend_get_requests' );

/**
 * Disable Email Sharing if Recaptcha is not setup.
 *
 * To prevent spam and abuse, we should only allow sharing via e-mail when reCAPTCHA is enabled.
 *
 * @see https://jetpack.com/support/sharing/#captcha Instructions on how to set up reCAPTCHA for your site
 *
 * @param  bool $is_enabled Current value.
 * @return bool              Whether (true) or not (false) email sharing is enabled.
 */
function wpcom_vip_disable_jetpack_email_no_recaptcha( $is_enabled ) {
	if ( ! $is_enabled ) {
		return $is_enabled;
	}

	return defined( 'RECAPTCHA_PUBLIC_KEY' ) && defined( 'RECAPTCHA_PRIVATE_KEY' );
}
if ( defined( 'JETPACK__VERSION' ) && JETPACK__VERSION < '11.0' ) {
	add_filter( 'sharing_services_email', 'wpcom_vip_disable_jetpack_email_no_recaptcha', PHP_INT_MAX );
}

/**
 * Enable the new Full Sync method on sites with the VIP_JETPACK_FULL_SYNC_IMMEDIATELY constant
 */
add_filter( 'jetpack_sync_modules', function ( $modules ) {
	if ( ! class_exists( 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync_Immediately' ) ) {
		return $modules;
	}

	if ( defined( 'VIP_JETPACK_FULL_SYNC_IMMEDIATELY' ) && true === VIP_JETPACK_FULL_SYNC_IMMEDIATELY ) {
		foreach ( $modules as $key => $module ) {
			// Replace Jetpack_Sync_Modules_Full_Sync or Full_Sync with the new module
			if ( in_array( $module, [ 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync', 'Jetpack_Sync_Modules_Full_Sync' ], true ) ) {
				$modules[ $key ] = 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync_Immediately';
			}
		}
	}

	return $modules;
} );

/**
 * Hide promotions/upgrade cards for now except for sites that we want to opt into Instant Search
 */
add_filter( 'jetpack_show_promotions', function ( $is_enabled ) {
	if ( defined( 'VIP_JETPACK_ENABLE_INSTANT_SEARCH' ) && true === VIP_JETPACK_ENABLE_INSTANT_SEARCH ) {
		return $is_enabled;
	}

	return false;
} );

/**
 * Hide Jetpack's just in time promotions
 */
add_filter( 'jetpack_just_in_time_msgs', '__return_false' );

/**
 * Custom CSS tweaks for the Jetpack Admin pages
 */
function vip_jetpack_admin_enqueue_scripts() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is unavailable here
	if ( ! isset( $_GET['page'] ) || 'jetpack' !== $_GET['page'] ) {
		return;
	}
	$admin_css_url = plugins_url( '/css/admin-settings.css', __FILE__ );
	wp_enqueue_style( 'vip-jetpack-admin-settings', $admin_css_url, [], '20200511' );
}

add_action( 'admin_enqueue_scripts', 'vip_jetpack_admin_enqueue_scripts' );

/**
 * A killswitch for Jetpack Sync Checksum functionality, either disable checksum when a Platform-wide constant is set and true or pass through the value to allow for app-side control.
 */
add_filter( 'pre_option_jetpack_sync_settings_checksum_disable', function ( $value ) {
	// phpcs:ignore WordPress.PHP.DisallowShortTernary.Found
	return defined( 'VIP_DISABLE_JETPACK_SYNC_CHECKSUM' ) && VIP_DISABLE_JETPACK_SYNC_CHECKSUM ?: $value;
} );

/**
 * SSL is always supported on VIP, so avoid unnecessary checks
 */
add_filter( 'pre_transient_jetpack_https_test', function () {
	return 1;
} ); // WP doesn't have __return_one (but it does have __return_zero)
add_filter( 'pre_transient_jetpack_https_test_message', '__return_empty_string' );

// And make sure this JP option gets filtered to 0 to prevent unnecessary checks. Can be removed from here when all supported versions include this fix: https://github.com/Automattic/jetpack/pull/18730
add_filter( 'jetpack_options', function ( $value, $name ) {
	if ( 'fallback_no_verify_ssl_certs' === $name ) {
		$value = 0;
	}

	return $value;
}, 10, 2 );

/**
 * Dummy Jetpack menu item if no other menu items are rendered
 */
function add_jetpack_menu_placeholder(): void {
	$status = new Automattic\Jetpack\Status();
	// is_connection_ready only exists in Jetpack 9.6 and newer
	if ( ! $status->is_offline_mode() && method_exists( 'Jetpack', 'is_connection_ready' ) && ! Jetpack::is_connection_ready() ) {
		add_submenu_page( 'jetpack', 'Connect Jetpack', 'Connect Jetpack', 'manage_options', 'jetpack' );
	}
}

add_action( 'admin_menu', 'add_jetpack_menu_placeholder', 999 );

/**
 * Remove the page that allows you to toggle Search and Instant Search on and off.
 * Use code or CLI instead to toggle Search module and open a ZD ticket for enabling Instant Search.
 *
 * @return void
 */
function vip_remove_jetpack_search_menu_page() {
	remove_submenu_page(
		'jetpack',
		'jetpack-search'
	);
}
add_action( 'admin_menu', 'vip_remove_jetpack_search_menu_page', PHP_INT_MAX );

/**
 * Account for X-Mobile-Class header in jetpack_is_mobile()
 *
 * @param bool|string $matches Boolean if current UA matches $kind or not. If
 * $return_matched_agent is true, should return the UA string
 * @param string      $kind Category of mobile device being checked. Can be 'any', 'smart' or 'dumb'.
 * @param bool        $return_matched_agent Boolean indicating if the UA should be returned
 *
 * @return bool|string $matches Boolean if current UA matches $kind or not. If
 * $return_matched_agent is true, should return the UA string
 */
function vip_jetpack_is_mobile( $matches, $kind, $return_matched_agent ) {
	if ( ! isset( $_SERVER['HTTP_X_MOBILE_CLASS'] ) || $return_matched_agent ) {
		// No value set or expectation to return matched agent, return early.
		return $matches;
	}

	// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
	$x_mobile_class = sanitize_text_field( $_SERVER['HTTP_X_MOBILE_CLASS'] ); // "desktop", "smart", "dumb", "tablet"

	if ( 'desktop' === $x_mobile_class ) {
		return false;
	}

	if ( 'smart' === $kind || 'dumb' === $kind ) {
		$matches = $kind === $x_mobile_class;
	} elseif ( 'any' === $kind ) {
		$matches = true;
	}

	return $matches;
}

add_filter( 'pre_jetpack_is_mobile', 'vip_jetpack_is_mobile', PHP_INT_MAX, 3 );

/**
 * Display correct Jetpack version in wp-admin plugins UI for pinned, local or default versions.
 *
 * @param string[]  plugin_meta  An array of the plugin's metadata, including
 *                               the version, author, author URI, and plugin URI.
 * @param string    $plugin_file Path to the plugin file relative to the plugins directory.
 * @return string[] $plugin_meta Updated plugin's metadata.
 */
function vip_filter_plugin_version_jetpack( $plugin_meta, $plugin_file ) {
	if ( defined( 'VIP_JETPACK_SKIP_LOAD' ) && constant( 'VIP_JETPACK_SKIP_LOAD' ) ) {
		return $plugin_meta;
	}

	if ( 'jetpack.php' === $plugin_file ) {
		$type = defined( 'WPCOM_VIP_JETPACK_LOCAL' ) && constant( 'WPCOM_VIP_JETPACK_LOCAL' ) ? '(Local)' : '';
		/* translators: Loaded Jetpack version number */
		$plugin_meta[0] = sprintf( esc_html__( 'Version %1$s %2$s' ), constant( 'JETPACK__VERSION' ), $type );
		remove_filter( 'plugin_row_meta', 'vip_filter_plugin_version_jetpack', PHP_INT_MAX, 2 );
	}

	return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'vip_filter_plugin_version_jetpack', PHP_INT_MAX, 2 );

/**
 * Enable Jetpack offline mode for Multisites when we're launching a site in the network.
 * This is enabled only if the site is a multisite.
 *
 * The function checks for the `launching` flag in the `vip_launch_tools` cache group.
 * If the flag is set to `true`, it enables the offline mode while the flag is active, blocking
 * the Jetpack communications from running.
 *
 * @param bool $offline_mode Whether to enable offline mode.
 * @return bool
 */
function vip_filter_jetpack_offline_mode_on_site_launch( $offline_mode ) {
	// If not multisite, return the offline mode value.
	if ( ! is_multisite() ) {
		return $offline_mode;
	}
	$vip_site_launching = wp_cache_get( 'launching', 'vip_launch_tools' );
	if ( 'true' === $vip_site_launching || true === $vip_site_launching ) {
		// enables jetpack offline mode
		return true;
	}
	// keep the offline mode as it was.
	return $offline_mode;
}

add_filter( 'jetpack_offline_mode', 'vip_filter_jetpack_offline_mode_on_site_launch', PHP_INT_MAX, 1 );

/**
 * Prevent admin/support users from spawning (useless, autoloaded) NULL value post_by_email_address* options.
 * Addresses https://github.com/Automattic/jetpack/issues/35636
 */
function vip_prevent_jetpack_post_by_email_database_noise() {
	// Prevent saving an unnecessary NULL option to the database.
	add_filter( 'pre_update_option_post_by_email_address' . get_current_user_id(), function ( $value, $old_value ) {
		if ( 'NULL' === $value ) {
			return $old_value;
		}

		return $value;
	}, 10, 2 );

	// Prevent unnecessary API calls for finding the remote email address when the module is disabled.
	if ( method_exists( 'Jetpack', 'is_module_active' ) && ! Jetpack::is_module_active( 'post-by-email' ) ) {
		add_filter( 'pre_option_post_by_email_address' . get_current_user_id(), function () {
			return 'NULL';
		} );
	}
}

add_action( 'admin_init', 'vip_prevent_jetpack_post_by_email_database_noise' );

/*
 * Workaround: prevent the "Invite user to WordPress.com" checkbox on "Add New User" page
 * from blocking new user registration on Jetpack 13.2.
 */
add_action( 'muplugins_loaded', function () {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['invite_user_wpcom'] ) ) {
		unset( $_POST['invite_user_wpcom'] );
	}
});

/**
 * Jetpack 13.9 removes the legacy Jetpack_SSO class. Unfortunately, this class is still used by
 * the deprecated standalone jetpack-force-2fa plugin (which is still in use by some). This is a dummy
 * class to prevent fatal errors when the standalone plugin is enabled.
 */
add_action( 'plugins_loaded', function () {
	if ( class_exists( 'Jetpack_Force_2FA' ) && ! class_exists( 'Jetpack_SSO' ) && class_exists( 'Jetpack' ) &&
		defined( 'JETPACK__VERSION' ) && version_compare( JETPACK__VERSION, '13.9', '>=' ) ) {
		require_once __DIR__ . '/jetpack-sso-dummy.php';
	}
} );
