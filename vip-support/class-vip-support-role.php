<?php

/**
 * Provides the VIP Support role
 *
 * @package WPCOM_VIP_Support_Role
 **/
class WPCOM_VIP_Support_Role {

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
	 * Initiate an instance of this class if one doesn't
	 * exist already. Return the VipSupportRole instance.
	 *
	 * @access @static
	 *
	 * @return WPCOM_VIP_Support_Role object The instance of WPCOM_VIP_Support_Role
	 */
	static public function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new WPCOM_VIP_Support_Role;
		}

		return $instance;

	}

	/**
	 * Class constructor. Handles hooking actions and filters,
	 * and sets some properties.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_filter( 'editable_roles', array( $this, 'filter_editable_roles' ) );
		add_filter( 'user_has_cap', array( $this, 'filter_user_has_cap' ), 10, 4 );
	}

	// HOOKS
	// =====

	/**
	 * Hooks the init action to add the role, covering the cases
	 * where we should be using `wpcom_vip_add_role`.
	 */
	public function action_init() {
		self::add_role();
	}

	/**
	 * Hooks the admin_init action to run an update method.
	 */
	public function action_admin_init() {
		$this->update();
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
	 * @param array   $args      Optional parameters passed to has_cap(), typically object ID.
	 * @param WP_User $user      The user object.
	 *
	 * @return array An array of all the user's caps, with the required cap added
	 */
	public function filter_user_has_cap( array $user_caps, array $caps, array $args, WP_User $user ) {
		if ( in_array( self::VIP_SUPPORT_ROLE, $user->roles ) && is_proxied_automattician() ) {
			foreach ( $caps as $cap ) {
				$user_caps[$cap] = true;
			}
		}
		return $user_caps;
	}

	/**
	 * Hooks the editable_roles filter to place the VIP Support at the bottom of
	 * any roles listing.
	 *
	 * @param array $roles An array of WP role data
	 *
	 * @return array An array of WP role data
	 */
	public function filter_editable_roles( array $roles ) {
		$vip_support_roles = array(
			self::VIP_SUPPORT_INACTIVE_ROLE => $roles[self::VIP_SUPPORT_INACTIVE_ROLE],
			self::VIP_SUPPORT_ROLE => $roles[self::VIP_SUPPORT_ROLE],
		);
		unset( $roles[self::VIP_SUPPORT_INACTIVE_ROLE] );
		unset( $roles[self::VIP_SUPPORT_ROLE] );
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
			error_log( $message );
		}

	}

	protected static function add_role() {
		if ( function_exists( 'wpcom_vip_add_role' ) ) {
			wpcom_vip_add_role( self::VIP_SUPPORT_ROLE, __( 'VIP Support', 'a8c_vip_support' ), array( 'read' => true ) );
			wpcom_vip_add_role( self::VIP_SUPPORT_INACTIVE_ROLE, __( 'VIP Support (inactive)', 'a8c_vip_support' ), array( 'read' => true ) );
		} else {
			add_role( self::VIP_SUPPORT_ROLE, __( 'VIP Support', 'a8c_vip_support' ), array( 'read' => true ) );
			add_role( self::VIP_SUPPORT_INACTIVE_ROLE, __( 'VIP Support (inactive)', 'a8c_vip_support' ), array( 'read' => true ) );
		}
	}

	/**
	 * Checks the version option value against the version
	 * property value, and runs update routines as appropriate.
	 *
	 */
	protected function update() {
		$option_name = 'vipsupportrole_version';
		$version = absint( get_option( $option_name, 0 ) );

		if ( $version == self::VERSION ) {
			return;
		}

		if ( $version < 1 && function_exists( 'wpcom_vip_add_role' ) ) {
			self::add_role();
			self::error_log( "VIP Support Role: Added VIP Support role " );
		}

		// N.B. Remember to increment self::VERSION above when you add a new IF

		update_option( $option_name, self::VERSION );
		$this->error_log( "VIP Support Role: Done upgrade, now at version " . self::VERSION );

	}
}

WPCOM_VIP_Support_Role::init();
