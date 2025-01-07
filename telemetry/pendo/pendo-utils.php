<?php
/**
 * Telemetry: Pendo Utils
 *
 * @package Automattic\VIP\Telemetry\Pendo
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\VIP\Support_User\User as Support_User;


/**
 * Returns the base properties for a track user.
 *
 * @return array<string, mixed> The base properties.
 */
function get_base_properties_of_pendo_user( $property_suffix ): array {
	if ( ! function_exists( 'wp_get_current_user' ) ) {
		return [];
	}

	$props = [];

	// Only track logged-in users.
	$wp_user = wp_get_current_user();
	if ( 0 === $wp_user->ID ) {
		return $props;
	}

	$is_vip_user = Support_User::user_has_vip_support_role( $wp_user->ID );
	
	// Set anonymized event user ID; it should be consistent across environments.
	// VIP_TELEMETRY_SALT is a private constant shared across the platform.
	if ( ! defined( 'VIP_TELEMETRY_SALT' ) && $wp_user->user_email ) {
		// If we can't set the user ID, don't track the user.
		return $props;
	}

	$salt           = constant( 'VIP_TELEMETRY_SALT' );
	$tracks_user_id = hash_hmac( 'sha256', $wp_user->user_email, $salt );

	$props['id']                             = $is_vip_user ? 'vip-' . $tracks_user_id : $tracks_user_id;
	$props[ 'full_name' . $property_suffix ] = $wp_user->display_name;
	$props[ 'role' . $property_suffix ]      = $wp_user->roles[0];

	return $props;
}
