<?php
/**
 * Telemetry: Tracks Utils
 *
 * @package Automattic\VIP\Telemetry\Tracks
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use Automattic\VIP\Support_User\User as Support_User;

/**
 * Returns the base properties for a track event.
 *
 * @return array<string, mixed> The base properties.
 */
function get_base_properties_of_track_event(): array {
	$props = [
		'hosting_provider' => get_hosting_provider(),
		'is_vip_user'      => Support_User::user_has_vip_support_role( get_current_user_id() ),
		'is_multisite'     => is_multisite(),
		'wp_version'       => get_bloginfo( 'version' ),
	];

	// Set VIP environment if it exists.
	if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
		$app_environment = constant( 'VIP_GO_APP_ENVIRONMENT' );
		if ( is_string( $app_environment ) && '' !== $app_environment ) {
			$props['vipgo_env'] = $app_environment;
		}
	}

	// Set VIP organization if it exists.
	if ( defined( 'VIP_ORG_ID' ) ) {
		$org_id = constant( 'VIP_ORG_ID' );
		if ( is_integer( $org_id ) && $org_id > 0 ) {
			$props['vipgo_org'] = $org_id;
		}
	}

	return $props;
}

/**
 * Check if the site is hosted on VIP.
 */
function is_wpvip_site(): bool {
	if ( is_wpvip_sandbox() ) {
		return false;
	}

	return defined( 'WPCOM_IS_VIP_ENV' ) && constant( 'WPCOM_IS_VIP_ENV' ) === true;
}

/**
 * Check if the site is hosted on VIP sandbox.
 */
function is_wpvip_sandbox(): bool {
	return defined( 'WPCOM_SANDBOXED' ) && constant( 'WPCOM_SANDBOXED' ) === true;
}

/**
 * Get the hosting provider.
 */
function get_hosting_provider(): string {
	if ( is_wpvip_sandbox() ) {
		return 'wpvip_sandbox';
	}

	if ( is_wpvip_site() ) {
		return 'wpvip';
	}

	return 'other';
}

/**
 * Returns the base properties for a track user.
 *
 * @return array<string, mixed> The base properties.
 */
function get_base_properties_of_track_user(): array {
	$props = [];

	// Only track logged-in users.
	$wp_user = wp_get_current_user();
	if ( 0 === $wp_user->ID ) {
		return $props;
	}

	// Set anonymized event user ID; it should be consistent across environments.
	// VIP_TELEMETRY_SALT is a private constant shared across the platform.
	if ( defined( 'VIP_TELEMETRY_SALT' ) ) {
		$salt           = constant( 'VIP_TELEMETRY_SALT' );
		$tracks_user_id = hash_hmac( 'sha256', $wp_user->user_email, $salt );

		$props['_ui'] = $tracks_user_id;
		$props['_ut'] = 'vip:user_email';
	} elseif ( defined( 'VIP_GO_APP_ID' ) ) {
		// Users in the VIP environment.
		$app_id = constant( 'VIP_GO_APP_ID' );
		if ( is_integer( $app_id ) && $app_id > 0 ) {
			$props['_ui'] = sprintf( '%s_%s', $app_id, $wp_user->ID );
			$props['_ut'] = 'vip_go_app_wp_user';
		}
	} else {
		// All other environments.
		$props['_ui'] = wp_hash( sprintf( '%s|%s', get_option( 'home' ), $wp_user->ID ) );

		/**
		 * @see \Automattic\VIP\Parsely\Telemetry\Tracks_Event::annotate_with_id_and_type()
		 */
		$props['_ut'] = 'anon'; // Same as the default value in the original code.
	}

	return $props;
}

/**
 * Get the core properties for a Tracks event.
 *
 * @return array<string, mixed> The core properties.
 */
function get_tracks_core_properties(): array {
	return array_merge( get_base_properties_of_track_event(), get_base_properties_of_track_user() );
}
