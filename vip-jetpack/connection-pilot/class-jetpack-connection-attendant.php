<?php

namespace Automattic\VIP\Jetpack\Connection_Pilot;

use WP_Error;

/**
 * The Attendant is responsible for managing the primary Jetpack connection owner.
 */
class Attendant {
	private static $instance = null;

	public static function instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Security Enhancements
		add_filter( 'authenticate', [ $this, 'prevent_login' ], 30, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', [ $this, 'disable_application_passwords' ], 15, 2 );
		add_filter( 'map_meta_cap', [ $this, 'modify_user_capabilties' ], PHP_INT_MAX, 4 );
		add_filter( 'wpcom_vip_is_two_factor_forced', [ $this, 'bypass_two_factor_auth' ], PHP_INT_MAX );
		add_action( 'plugins_loaded', [ $this, 'remove_my_jetpack_page' ], 30 );
	}

	/*
	|--------------------------------------------------------------------------
	| Owner Configuration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if the current Jetpack connection owner is the VIP user.
	 *
	 * @return string 'is_vip' | 'is_legacy_vip' | 'not_vip'
	 */
	public function check_connection_owner_validity() {
		$expected_owner_details = $this->get_expected_owner_details();
		$legacy_owner_details   = $this->get_legacy_owner_details();
		$org_owner_details      = $this->get_org_owner_details();

		$current_owner = $this->get_current_connection_owner();
		if ( ! isset( $current_owner->user_login ) ) {
			return 'not_vip';
		}

		// We won't consider it the legacy user if it is still the expected owner.
		if ( $expected_owner_details['login'] === $current_owner->user_login ) {
			return 'is_vip';
		}

		if ( $legacy_owner_details && $legacy_owner_details['login'] === $current_owner->user_login ) {
			return 'is_legacy_vip';
		}

		return 'not_vip';
	}

	/**
	 * Returns the user object of the connection owner.
	 *
	 * @return WP_User|null Null if no connection owner found.
	 */
	private function get_current_connection_owner() {
		if ( ! method_exists( 'Jetpack', 'connection' ) ) {
			return null;
		}

		$jp_connection = \Jetpack::connection();

		if ( ! method_exists( $jp_connection, 'get_connection_owner' ) ) {
			return null;
		}

		$connection_owner = $jp_connection->get_connection_owner();
		if ( false === $connection_owner ) {
			return null;
		}

		return $connection_owner;
	}

	/**
	 * Ensures the VIP user exists on the site and has the necessary permissions.
	 *
	 * @return WP_User|WP_Error WP_User if successful, WP_Error otherwise.
	 */
	public function ensure_user_exists() {
		$details = $this->get_expected_owner_details();

		$user = get_user_by( 'login', $details['login'] );

		// Create user if it doesn't exist yet.
		if ( ! $user ) {
			$result = wp_insert_user( [
				'user_login'   => $details['login'],
				'user_email'   => $details['email'],
				'display_name' => $details['display_name'],
				'role'         => $details['role'],
				'user_pass'    => wp_generate_password( 100 ),
			] );

			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'jp-cxn-pilot-owner-creation-failed', 'Failed to create new user.' );
			}

			$user = get_userdata( $result );
			if ( ! $user ) {
				return new WP_Error( 'jp-cxn-pilot-owner-creation-failed', 'Failed to create new user.' );
			}
		}

		// Ensure the email is correct & hasn't been changed.
		if ( $details['email'] !== $user->user_email ) {
			$result = wp_update_user( [
				'ID'         => $user->ID,
				'user_email' => $details['email'],
			] );

			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'jp-cxn-pilot-owner-update-failed', 'Failed to correct the email address on the primary user.' );
			}
		}

		// Add user to blog if needed, and ensure they are a super admin.
		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			if ( ! is_user_member_of_blog( $user->ID, $blog_id ) ) {
				$added_to_blog = add_user_to_blog( $blog_id, $user->ID, $details['role'] );

				if ( is_wp_error( $added_to_blog ) ) {
					return new WP_Error( 'jp-cxn-pilot-owner-ms-add-failed', 'Failed to add user to blog. Error: ' . $added_to_blog->get_error_code() );
				}
			}

			if ( ! is_super_admin( $user->ID ) ) {
				// Returns false even if user already is SA.
				grant_super_admin( $user->ID );
			}
		}

		// Ensure the correct role is applied.
		if ( ! in_array( $details['role'], (array) $user->roles, true ) ) {
			$user->set_role( $details['role'] );
		}

		return $user;
	}

	private function get_expected_owner_details() {
		$legacy_owner_details = $this->get_legacy_owner_details();

		// NOTE: This filter should be applied very early. Use before plugins_loaded priority 25 to be sure.
		if ( null !== $legacy_owner_details && ! (bool) apply_filters( 'vip_jetpack_connection_pilot_use_org_owner', false ) ) {
			return $legacy_owner_details;
		}

		return $this->get_org_owner_details();
	}

	private function get_org_owner_details() {
		$org_id = defined( 'VIP_ORG_ID' ) ? constant( 'VIP_ORG_ID' ) : 0;

		return [
			'email'        => "vip-jetpack-owner+{$org_id}@wpvip.com",
			'display_name' => 'WPVIP Jetpack Connection Owner',
			'login'        => 'wpvip-jetpack-connection-owner',
			'role'         => 'administrator',
		];
	}

	private function get_legacy_owner_details() {
		$required_constants = [
			defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ),
			defined( 'WPCOM_VIP_MACHINE_USER_ROLE' ),
			defined( 'WPCOM_VIP_MACHINE_USER_NAME' ),
			defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ),
		];

		if ( in_array( false, $required_constants, true ) ) {
			return null;
		}

		return [
			'email'        => constant( 'WPCOM_VIP_MACHINE_USER_EMAIL' ),
			'display_name' => constant( 'WPCOM_VIP_MACHINE_USER_NAME' ),
			'login'        => constant( 'WPCOM_VIP_MACHINE_USER_LOGIN' ),
			'role'         => constant( 'WPCOM_VIP_MACHINE_USER_ROLE' ),
		];
	}

	/*
	|--------------------------------------------------------------------------
	| Security Enhancements
	|--------------------------------------------------------------------------
	*/

	/**
	 * Do not allow logging in to this owner account.
	 * Note that core runs authentication at priority 20.
	 *
	 * @param null|WP_User|WP_Error $user              WP_User if the user is authenticated, WP_Error or null otherwise.
	 * @param string                $username_or_email The username/email of the user attempting to authenticate.
	 * @return null|WP_User|WP_Error
	 */
	public function prevent_login( $user, $username_or_email ) {
		$details = $this->get_org_owner_details();

		if ( in_array( strtolower( $username_or_email ), [ $details['login'], $details['email'] ], true ) ) {
			return new WP_Error( 'restricted-login', 'Logins are restricted for that user. Please try a different user account.' );
		}

		return $user;
	}

	/**
	 * Disallow using application passwords for this owner account.
	 *
	 * @param bool    $available True if available, false otherwise.
	 * @param WP_User $user      The user to check.
	 * @return bool
	 */
	public function disable_application_passwords( $available, $user ) {
		$details = $this->get_org_owner_details();

		if ( $details['login'] === $user->user_login ) {
			$available = false;
		}

		return $available;
	}

	/**
	 * Restrict modifications to the designated connection owner, and allow
	 * only this connection owner to manage the Jetpack connection's state.
	 *
	 * @param string[] $caps    Primitive capabilities required of the user.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id The user ID.
	 * @param array    $args    Adds context to the capability check.
	 *
	 * @return string[] The capabilities required of the given user to satisfy the capability being checked.
	 */
	public function modify_user_capabilties( $caps, $requested_cap, $user_id, $args ) {
		$caps_to_prevent = [ 'edit_user', 'delete_user', 'remove_user', 'promote_user' ];
		if ( in_array( $requested_cap, $caps_to_prevent, true ) ) {
			$user_being_edited = get_userdata( $args[0] );

			// Only the designated connection owner needs to be prevented from modifications.
			$org_owner = $this->get_org_owner_details();
			if ( false !== $user_being_edited && $org_owner['login'] === $user_being_edited->user_login ) {
				return [ 'do_not_allow' ];
			}
		}

		$caps_to_restrict = [ 'jetpack_connect', 'jetpack_reconnect', 'jetpack_disconnect' ];
		$is_vip_env       = defined( 'WPCOM_IS_VIP_ENV' ) && true === constant( 'WPCOM_IS_VIP_ENV' );
		if ( $is_vip_env && in_array( $requested_cap, $caps_to_restrict, true ) ) {
			$user = get_userdata( $user_id );

			// All users except the designated connection owners are restricted from managing connections.
			$legacy_owner = $this->get_legacy_owner_details();
			$org_owner    = $this->get_org_owner_details();
			if ( false !== $user && ! in_array( $user->user_login, [ $legacy_owner['login'] ?? 'wpcomvip', $org_owner['login'] ], true ) ) {
				return [ 'do_not_allow' ];
			}
		}

		return $caps;
	}

	/**
	 * Bypass the VIP two-factor authentication requirement for the designated connection owner,
	 * otherwise Jetpack doesn't think the owner has any capabilities beyond subscriber.
	 * Note that logins for this account are already disabled.
	 *
	 * @return boolean
	 */
	public function bypass_two_factor_auth( $is_two_factor_forced ) {
		$current_user = wp_get_current_user();

		$org_owner = $this->get_org_owner_details();
		if ( $current_user->user_login === $org_owner['login'] ) {
			$is_two_factor_forced = false;
		}

		return $is_two_factor_forced;
	}

	/**
	 * Tell Jetpack to not initialize the "My Jetpack" page when the connection owner is the bot user.
	 *
	 * @return void
	 */
	public function remove_my_jetpack_page() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		if ( in_array( $this->check_connection_owner_validity(), [ 'is_vip', 'is_legacy_vip' ], true ) ) {
			add_filter( 'jetpack_my_jetpack_should_initialize', '__return_false' );
		}
	}
}
