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
 * Add the Connection Pilot. Ensures Jetpack is consistently connected.
 */
require_once( __DIR__ . '/connection-pilot/class-jetpack-connection-pilot.php' );

/**
 * Enable VIP modules required as part of the platform
 */
require_once( __DIR__ . '/jetpack-mandatory.php' );

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

		wp_die( sprintf( '😱😱😱 Oh no! Looks like your sandbox is trying to change the version of Jetpack (from %1$s => %2$s). This is probably not a good idea. As a precaution, we\'re killing this request to prevent potentially bad things. Please run `vip stacks update` on your sandbox before doing anything else.', $old_version, $new_version ), 400 );
	}, 0, 2 ); // No need to wait till priority 10 since we're going to die anyway
}

// On production servers, only our machine user can manage the Jetpack connection
if ( true === WPCOM_IS_VIP_ENV && is_admin() ) {
	add_filter( 'map_meta_cap', function( $caps, $cap, $user_id, $args ) {
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
	}, 10, 4 );
}

function wpcom_vip_did_jetpack_search_query( $query ) {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return;
	}

	global $wp_elasticsearch_queries_log;

	if ( ! isset( $wp_elasticsearch_queries_log ) || ! is_array( $wp_elasticsearch_queries_log ) ) {
		$wp_elasticsearch_queries_log = array();
	}

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
add_filter( 'sharing_services_email', 'wpcom_vip_disable_jetpack_email_no_recaptcha' );
