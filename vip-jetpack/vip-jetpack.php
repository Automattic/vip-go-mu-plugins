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

// Default plan object for all VIP sites.
define( 'VIP_JETPACK_DEFAULT_PLAN', array(
	'product_id'         => 'vip',
	'product_slug'       => 'vip',
	'product_name_short' => 'VIP',
	'product_variation'  => 'vip',
	'supports'           => array(
		'videopress',
		'akismet',
		'vaultpress',
		'seo-tools',
		'google-analytics',
		'wordads',
		'search',
	),
	'features'           => array(
		'active'    => array(
			'premium-themes',
			'google-analytics',
			'security-settings',
			'advanced-seo',
			'upload-video-files',
			'video-hosting',
			'send-a-message',
			'whatsapp-button',
			'social-previews',
			'donations',
			'core/audio',
			'republicize',
			'premium-content/container',
			'akismet',
			'vaultpress-backups',
			'vaultpress-backup-archive',
			'vaultpress-storage-space',
			'vaultpress-automated-restores',
			'vaultpress-security-scanning',
			'polldaddy',
			'simple-payments',
			'support',
			'wordads-jetpack',
		),
		'available' => array(
			'security-settings'             => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'advanced-seo'                  => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'upload-video-files'            => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'akismet'                       => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'send-a-message'                => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'whatsapp-button'               => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'social-previews'               => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'google-analytics'              => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'video-hosting'                 => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'wordads-jetpack'               => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'vaultpress-backups'            => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'vaultpress-backup-archive'     => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'vaultpress-storage-space'      => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'vaultpress-automated-restores' => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'simple-payments'               => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
			),
			'calendly'                      => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'opentable'                     => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'donations'                     => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'core/video'                    => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'core/cover'                    => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'core/audio'                    => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'republicize'                   => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'premium-content/container'     => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'support'                       => array(
				'jetpack_premium',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'premium-themes'                => array(
				'jetpack_business',
				'jetpack_business_monthly',
			),
			'vaultpress-security-scanning'  => array(
				'jetpack_business',
				'jetpack_business_monthly',
			),
			'polldaddy'                     => array(
				'jetpack_business',
				'jetpack_business_monthly',
			),
		),
	),
) );

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
add_filter( 'jetpack_get_available_modules', function( $modules ) {
	// The Photon service is not necessary on VIP Go since the same features are built-in.
	// Note that we do utilize some of the Photon module's code with our own Files Service.
	unset( $modules['photon'] );
	unset( $modules['photon-cdn'] );

	unset( $modules['site-icon'] );
	unset( $modules['protect'] );

	return $modules;
}, 999 );

/**
 * Prevent the jetpack_active_plan from ever being overridden.
 *
 * All sites on VIP Go should always have have a valid VIP plan.
 *
 * This will prevent issues from the plan option being corrupted,
 * which can then break features like Jetpack Search.
 */
add_filter( 'pre_option_jetpack_active_plan', function( $pre_option ) {
	if ( true === WPCOM_IS_VIP_ENV
		&& defined( 'VIP_JETPACK_DEFAULT_PLAN' )
		&& Jetpack::is_active() ) {
		return VIP_JETPACK_DEFAULT_PLAN;
	}

	return $pre_option;
} );

/**
 * Lock down the jetpack_sync_settings_max_queue_size to an allowed range
 * 
 * Still allows changing the value per site, but locks it into the range
 */
add_filter( 'option_jetpack_sync_settings_max_queue_size', function( $value ) {
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
add_filter( 'option_jetpack_sync_settings_max_queue_lag', function( $value ) {
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
add_filter( 'option_jetpack_sync_settings_cron_sync_time_limit', function() {
	return 4 * MINUTE_IN_SECONDS;
}, 9999 );

/**
 * Reduce the time between sync batches on VIP for performance gains.
 *
 * By default, this is 10 seconds, but VIP can be more aggressive and doesn't need to wait as long (we'll still wait a small amount).
 * 
 */
add_filter( 'option_jetpack_sync_settings_sync_wait_time', function() {
	return 1;
}, 9999 );

// Prevent Jetpack version ping-pong when a sandbox has an old version of stacks
if ( true === WPCOM_SANDBOXED ) {
	add_action( 'updating_jetpack_version', function( $new_version, $old_version ) {
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

// On production servers, only our machine user can manage the Jetpack connection
if ( true === WPCOM_IS_VIP_ENV && is_admin() ) {
	add_filter( 'map_meta_cap', function( $caps, $cap, $user_id ) {
		switch ( $cap ) {
			case 'jetpack_connect':
			case 'jetpack_reconnect':
			case 'jetpack_disconnect':
				$user = get_userdata( $user_id );
				if ( $user && WPCOM_VIP_MACHINE_USER_LOGIN !== $user->user_login ) {
					return [ 'do_not_allow' ];
				}
				break;
		}

		return $caps;
	}, 10, 3 );
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
add_filter( 'sharing_services_email', 'wpcom_vip_disable_jetpack_email_no_recaptcha', PHP_INT_MAX );

/**
 * Enable the new Full Sync method on sites with the VIP_JETPACK_FULL_SYNC_IMMEDIATELY constant
 */
add_filter( 'jetpack_sync_modules', function( $modules ) {
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
add_filter( 'pre_option_jetpack_sync_settings_checksum_disable', function( $value ) {
	// phpcs:ignore WordPress.PHP.DisallowShortTernary.Found
	return defined( 'VIP_DISABLE_JETPACK_SYNC_CHECKSUM' ) && VIP_DISABLE_JETPACK_SYNC_CHECKSUM ?: $value;
} );

/**
 * SSL is always supported on VIP, so avoid unnecessary checks
 */
add_filter( 'pre_transient_jetpack_https_test', function() {
	return 1;
} ); // WP doesn't have __return_one (but it does have __return_zero)
add_filter( 'pre_transient_jetpack_https_test_message', '__return_empty_string' );

// And make sure this JP option gets filtered to 0 to prevent unnecessary checks. Can be removed from here when all supported versions include this fix: https://github.com/Automattic/jetpack/pull/18730
add_filter( 'jetpack_options', function( $value, $name ) {
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
