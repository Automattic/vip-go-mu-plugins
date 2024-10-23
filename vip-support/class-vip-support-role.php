<?php
/**
 * Support User Role
 */

namespace Automattic\VIP\Support_User;

use WP_User;

/**
 * Provides the VIP Support role
 *
 * @package WPCOM_VIP_Support_Role
 **/
class Role {

	/**
	 * The name of the ACTIVE VIP Support role
	 */
	const VIP_SUPPORT_ROLE = 'vip_support';

	/**
	 * The name of the INACTIVE VIP Support role
	 */
	const VIP_SUPPORT_INACTIVE_ROLE = 'vip_support_inactive';

	/**
	 * A version used to determine any necessary
	 * update routines to be run.
	 */
	const VERSION = 2;

	/**
	 * Capabilities that even VIP Support shouldn't have
	 *
	 * ie, filesystem is read-only, regardless of WP role
	 */
	const BANNED_CAPABILITIES = array(
		'edit_files',
		'edit_plugins',
		'edit_themes',
	);

	/**
	 * Initiate an instance of this class if one doesn't
	 * exist already. Return the Role instance.
	 *
	 * @access @static
	 *
	 * @return Role object The instance of Role
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new Role();
		}

		return $instance;
	}

	/**
	 * Class constructor. Handles hooking actions and filters,
	 * and sets some properties.
	 */
	public function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'wp_loaded', array( $this, 'action_admin_init' ) );
		} else {
			add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		}
		add_filter( 'editable_roles', array( $this, 'filter_editable_roles' ) );
		add_filter( 'user_has_cap', array( $this, 'filter_user_has_cap' ), PHP_INT_MAX, 4 );
		add_filter( 'site_option_site_admins', array( $this, 'filter_site_option_site_admins' ), PHP_INT_MAX );
	}

	// HOOKS
	// =====

	/**
	 * Hooks the admin_init action to run an update method.
	 */
	public function action_admin_init() {
		$this->maybe_upgrade_version();
	}

	/**
	 * Hooks the user_has_cap filter to allow VIP Support role users to do EVERYTHING
	 *
	 * Rather than explicitly adding all the capabilities to the admin role, and possibly
	 * missing some custom ones, or copying a role, and possibly being tripped up when
	 * that role doesn't exist, we filter all user capability checks and wave past our
	 * VIP Support users as automattically having the capability being checked.
	 *
	 * @param array   $user_caps An array of all the user's capabilities.
	 * @param array   $caps      Actual capabilities for meta capability.
	 * @param array   $_args     Optional parameters passed to has_cap(), typically object ID.
	 * @param WP_User $user      The user object.
	 *
	 * @return array An array of all the user's caps, with the required cap added
	 */
	public function filter_user_has_cap( array $user_caps, array $caps, array $_args, WP_User $user ) {
		if ( in_array( self::VIP_SUPPORT_ROLE, $user->roles ) && is_proxied_automattician() ) {
			$caps = array_diff( $caps, self::BANNED_CAPABILITIES );

			foreach ( $caps as $cap ) {
				$user_caps[ $cap ] = true;
			}
		}
		return $user_caps;
	}

	public function filter_site_option_site_admins( $site_admins ) {
		$user = wp_get_current_user();

		if ( in_array( self::VIP_SUPPORT_ROLE, $user->roles ) && is_proxied_automattician() ) {
			$site_admins[] = $user->user_login;
		}
		
		return $site_admins;
	}

	/**
	 * Hooks the editable_roles filter to remove the VIP Support roles for all users except VIP Support.
	 * For VIP Support users, it will place the role at the bottom of any roles listings.
	 *
	 * @param array $roles An array of WP role data
	 *
	 * @return array An array of WP role data
	 */
	public function filter_editable_roles( array $roles ) {
		$vip_support_roles = array();

		if ( isset( $roles[ self::VIP_SUPPORT_INACTIVE_ROLE ] ) ) {
			if ( current_user_can( 'vip_support' ) ) {
				$vip_support_roles[ self::VIP_SUPPORT_INACTIVE_ROLE ] = $roles[ self::VIP_SUPPORT_INACTIVE_ROLE ];
			}
			unset( $roles[ self::VIP_SUPPORT_INACTIVE_ROLE ] );
		}

		if ( isset( $roles[ self::VIP_SUPPORT_ROLE ] ) ) {
			if ( current_user_can( 'vip_support' ) ) {
				$vip_support_roles[ self::VIP_SUPPORT_ROLE ] = $roles[ self::VIP_SUPPORT_ROLE ];
			}
			unset( $roles[ self::VIP_SUPPORT_ROLE ] );
		}

		$roles = array_merge( $vip_support_roles, $roles );

		return $roles;
	}

	// UTILITIES
	// =========

	/**
	 * Log errors if WP_DEBUG is defined and true.
	 *
	 * @param string $message The message to log
	 */
	protected static function error_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}
	}

	protected static function add_roles() {
		wpcom_vip_add_role( self::VIP_SUPPORT_ROLE, __( 'VIP Support', 'a8c_vip_support' ), array( 'read' => true ) );
		wpcom_vip_add_role( self::VIP_SUPPORT_INACTIVE_ROLE, __( 'VIP Support (inactive)', 'a8c_vip_support' ), array( 'read' => true ) );
	}

	/**
	 * Checks the version option value against the version
	 * property value, and runs update routines as appropriate.
	 */
	public function maybe_upgrade_version() {
		$option_name = 'vipsupportrole_version';
		$version     = absint( get_option( $option_name, 0 ) );

		if ( self::VERSION === $version ) {
			return;
		}

		if ( $version < 1 ) {
			self::add_roles();
			self::error_log( 'VIP Support Role: Added VIP Support role ' );
		}

		// N.B. Remember to increment self::VERSION above when you add a new IF

		update_option( $option_name, self::VERSION );
		$this->error_log( 'VIP Support Role: Done upgrade, now at version ' . self::VERSION );
	}
}

Role::init();
