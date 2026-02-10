<?php
/**
 * Telemetry: Pendo Utils
 *
 * @package Automattic\VIP\Telemetry\Pendo
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use function Automattic\VIP\Telemetry\Tracks\get_base_properties_of_track_event;
use function Automattic\VIP\Telemetry\Tracks\get_base_properties_of_track_user;

/**
 * Returns the base properties for a Pendo event. Reuse the corresponding Tracks
 * function to reduce code duplication.
 *
 * @return array<string, mixed> The base properties.
 */
function get_base_properties_of_pendo_track_event(): array {
	return array_merge(
		get_base_properties_of_track_event(),
		[
			'environment_type'   => defined( 'WP_ENVIRONMENT_TYPE' ) ? constant( 'WP_ENVIRONMENT_TYPE' ) : 'unknown',
			'mu_plugins_version' => get_mu_plugins_version(),
		]
	);
}

/**
 * Returns the base properties for a user in the context of a Pendo event. Reuse
 * the corresponding Tracks function to reduce code duplication.
 *
 * @return null|array<string, string> The base properties.
 */
function get_base_properties_of_pendo_user(): array|null {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return null;
	}

	// Only track logged-in users.
	$wp_user = wp_get_current_user();
	if ( 0 === $wp_user->ID ) {
		return null;
	}

	$event_props = get_base_properties_of_pendo_track_event();
	$user_props  = get_base_properties_of_track_user();

	$is_vip_user = $event_props['is_vip_user'];
	$user_id     = $is_vip_user ? 'vip-' . $user_props['_ui'] : $user_props['_ui'];
	$vip_org_id  = $event_props['vip_org'] ?? 'unknown';
	$sf_org_id   = defined( 'VIP_SF_ACCOUNT_ID' ) ? constant( 'VIP_SF_ACCOUNT_ID' ) : sprintf( 'nosfid_wordpress_%s', $vip_org_id );

	return [
		'account_id'     => (string) $sf_org_id,
		'country_code'   => sanitize_text_field( $_SERVER['GEOIP_COUNTRY_CODE'] ?? 'unknown' ),
		'org_id'         => (string) $vip_org_id,
		'role_wordpress' => $wp_user->roles[0] ?? 'unknown', // The suffix helps prevent collisions with other contexts like the VIP Dashboard.
		'email'          => strtolower( sanitize_email( $wp_user->user_email ) ),
		'visitor_id'     => (string) $user_id,
		'visitor_name'   => $wp_user->display_name,
	];
}

/**
 * Returns the version of VIP mu-plugins.
 *
 * @return string The version.
 */
function get_mu_plugins_version(): string {
	if ( defined( 'WPVIP_MU_PLUGIN_DIR' ) && file_exists( constant( 'WPVIP_MU_PLUGIN_DIR' ) . '/.version' ) ) {
		return file_get_contents( constant( 'WPVIP_MU_PLUGIN_DIR' ) . '/.version' );
	}

	return 'unknown';
}
