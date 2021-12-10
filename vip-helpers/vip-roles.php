<?php
/**
 * Helper functions that make it easy to add roles for VIP sites.
 */

/**
 * Get a list of capabilities for a role.
 *
 * @param string $role Role name
 * @return array Array of caps for the role
 */
function wpcom_vip_get_role_caps( $role ) {
	$caps     = array();
	$role_obj = get_role( $role );

	if ( $role_obj && isset( $role_obj->capabilities ) ) {
		$caps = $role_obj->capabilities;
	}

	return $caps;
}

/**
 * Add a new role
 *
 * Usage: wpcom_vip_add_role( 'super-editor', 'Super Editor', array( 'level_0' => true ) );
 *
 * @param string $role Role name
 * @param string $name Display name for the role
 * @param array $capabilities Key/value array of capabilities for the role
 */
function wpcom_vip_add_role( $role, $name, $capabilities ) {
	$role_obj = get_role( $role );

	if ( ! $role_obj ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.custom_role_add_role
		add_role( $role, $name, $capabilities );

		_wpcom_vip_maybe_refresh_current_user_caps( $role );
	} else {
		wpcom_vip_merge_role_caps( $role, $capabilities );
	}
}

/**
 * Add new or change existing capabilities for a given role
 *
 * Usage: wpcom_vip_merge_role_caps( 'author', array( 'publish_posts' => false ) );
 *
 * @param string $role Role name
 * @param array $caps Key/value array of capabilities for this role
 */
function wpcom_vip_merge_role_caps( $role, $caps ) {
	$role_obj = get_role( $role );

	if ( ! $role_obj ) {
		return;
	}

	$current_caps = (array) wpcom_vip_get_role_caps( $role );
	$new_caps     = array_merge( $current_caps, (array) $caps );

	foreach ( $new_caps as $cap => $role_can ) {
		if ( $role_can ) {
			$role_obj->add_cap( $cap );
		} else {
			$role_obj->remove_cap( $cap );
		}
	}

	_wpcom_vip_maybe_refresh_current_user_caps( $role );
}

/**
 * Completely override capabilities for a given role
 *
 * Usage: wpcom_vip_override_role_caps( 'editor', array( 'level_0' => false ) );
 *
 * @param string $role Role name
 * @param array $caps Key/value array of capabilities for this role
 */
function wpcom_vip_override_role_caps( $role, $caps ) {
	$role_obj = get_role( $role );

	if ( ! $role_obj ) {
		return;
	}

	$role_obj->capabilities = (array) $caps;

	_wpcom_vip_maybe_refresh_current_user_caps( $role );
}

/**
 * Duplicate an existing role and modify some caps
 *
 * Usage: wpcom_vip_duplicate_role( 'administrator', 'station-administrator', 'Station Administrator', array( 'manage_categories' => false ) );
 *
 * @param string $from_role Role name
 * @param string $to_role_slug Role name
 * @param string $to_role_name Display name for the role
 * @param array $modified_caps Key/value array of capabilities for the role
 */
function wpcom_vip_duplicate_role( $from_role, $to_role_slug, $to_role_name, $modified_caps ) {
	$caps = array_merge( wpcom_vip_get_role_caps( $from_role ), $modified_caps );
	wpcom_vip_add_role( $to_role_slug, $to_role_name, $caps );
}

/**
 * Add capabilities to an existing role
 *
 * Usage: wpcom_vip_add_role_caps( 'contributor', array( 'upload_files' ) );
 *
 * @param string $role Role name
 * @param array $caps Capabilities to add to the role
 */
function wpcom_vip_add_role_caps( $role, $caps ) {
	$filtered_caps = array();
	foreach ( (array) $caps as $cap ) {
		$filtered_caps[ $cap ] = true;
	}
	wpcom_vip_merge_role_caps( $role, $filtered_caps );
}

/**
 * Remove capabilities from an existing role
 *
 * Usage: wpcom_vip_remove_role_caps( 'author', array( 'publish_posts' ) );
 *
 * @param string $role Role name
 * @param array $caps Capabilities to remove from the role
 */
function wpcom_vip_remove_role_caps( $role, $caps ) {
	$filtered_caps = array();
	foreach ( (array) $caps as $cap ) {
		$filtered_caps[ $cap ] = false;
	}
	wpcom_vip_merge_role_caps( $role, $filtered_caps );
}

/**
 * Force refreshes the current user's capabilities if they belong to the specified role.
 *
 * This is to prevent a race condition where the WP_User and its related caps are generated before or roles changes.
 *
 * @param string $role Role name
 */
function _wpcom_vip_maybe_refresh_current_user_caps( $role ) {
	if ( is_user_logged_in() && current_user_can( $role ) ) {
		wp_get_current_user()->get_role_caps();
	}
}
