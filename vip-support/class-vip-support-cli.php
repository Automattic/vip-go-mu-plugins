<?php

/**
 * Implements a WP CLI command that converts guid users to meta users
 * Class command
 *
 * @package a8c\vip_support
 */
class WPCOM_VIP_Support_CLI  extends WP_CLI_Command {


	/**
	 * Creates a user in the VIP Support role, already verified,
	 * and suppresses all emails.
	 *
	 * @subcommand create-user
	 *
	 * @synopsis <user-login> <user-email> <user-pass> [--display-name=<display-name>]
	 *
	 * ## EXAMPLES
	 *
	 *     wp vipsupport create-user username user@domain.tld user_password --display-name="display name"
	 *
	 */
	public function add_support_user( $args, $assoc_args ) {

		$user_login   = $args[0];
		$user_email   = $args[1];
		$user_pass    = $args[2];
		$display_name = $assoc_args['display-name'];

		// @TODO Check the email address is an A8c domain, will need to convert the method to static
		if ( ! is_email( $user_email ) ) {
			\WP_CLI::error( "User cannot be added as '{$user_email}' is not a valid email address" );
		}

		$user_data = array();
		$user_data['user_pass']    = $user_pass;
		$user_data['user_login']   = $user_login;
		$user_data['user_email']   = $user_email;
		$user_data['display_name'] = $display_name;

		// A user with this email address may already exist, in which case
		// we should update that user record
		$user = get_user_by( 'email', $user_email );

		$update_user = true;
		if ( false === $user ) {
			$update_user = false;
		}

		// If the user already exists, we should delete and recreate them,
		// it's the only way to be sure we get the right user_login
		if ( false !== $user && $user_login !== $user->user_login ) {
			if ( is_multisite() ) {
				revoke_super_admin( $user->ID );
				wpmu_delete_user( $user->ID );
			} else {
				wp_delete_user( $user->ID, null );
			}
			$user = false;
		} else {
			$user_data['ID'] = $user->ID;
		}

		if ( false === $user ) {
			$user_id = wp_insert_user( $user_data );
		} else {
			add_filter( 'send_password_change_email', '__return_false' );
			$user_id = wp_update_user( $user_data );
		}

		// It's possible the user update/insert will fail, we need to log this
		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::error( $user_id );
		}

		remove_action( 'set_user_role', array( WPCOM_VIP_Support_User::init(), 'action_set_user_role' ), 10, 3 );
		$user = new WP_User( $user_id );
		add_action( 'set_user_role', array( WPCOM_VIP_Support_User::init(), 'action_set_user_role' ), 10, 3 );

		// Seems polite to notify the admin that a support user got added to their site
		// @TODO Tailor the notification email so it explains that this is a support user
		wp_new_user_notification( $user_id, null, 'admin' );

		WPCOM_VIP_Support_User::init()->mark_user_email_verified( $user->ID, $user->user_email );
		$user->set_role( WPCOM_VIP_Support_Role::VIP_SUPPORT_ROLE );

		// If this is a multisite, commence super powers!
		if ( is_multisite() ) {
			grant_super_admin( $user->ID );
		}

		$msg = "Added";
		if ( $update_user ) {
			$msg = "Updated";
		}
		$msg .= " user $user_id with login {$user_login} and password '{$user_pass}', they are verified as a VIP Support user and ready to go";
		\WP_CLI::success( $msg );
	}

	/**
	 * Removes a user in the VIP Support role.
	 *
	 * @subcommand remove-user
	 *
	 * @synopsis <user-email>
	 *
	 * ## EXAMPLES
	 *
	 *     wp vipsupport remove-user user@domain.tld
	 *
	 */
	public function remove_support_user( $args, $assoc_args ) {

		$user_email = $args[0];

		// Let's find the user
		$user = get_user_by( 'email', $user_email );

		if ( false === $user ) {
			\WP_CLI::error( "No user exists with the email address {$user_email}, so they could not be deleted" );
		}

		// Check user has the active or inactive VIP Support role,
		// and bail out if not
		if ( ! WPCOM_VIP_Support_User::user_has_vip_support_role( $user->ID, true )
			&& ! WPCOM_VIP_Support_User::user_has_vip_support_role( $user->ID, false ) ) {
			\WP_CLI::error( "The user with email {$user_email} is not in the active or the inactive VIP Support roles" );
		}

		// If the user already exists, we should delete and recreate them,
		// it's the only way to be sure we get the right user_login
		if ( is_multisite() ) {
			revoke_super_admin( $user->ID );
			wpmu_delete_user( $user->ID );
		} else {
			wp_delete_user( $user->ID, null );
		}

		\WP_CLI::success( "Deleted user with email {$user_email}" );
	}


	/**
	 * Marks the user with the provided ID as having a verified email.
	 *
	 *
	 * <user-id>
	 * : The WP User ID to mark as having a verified email address
	 *
	 * @subcommand verify
	 *
	 * ## EXAMPLES
	 *
	 *     wp vipsupport verify 99
	 *
	 */
	public function verify( $args ) {

		$user_id = absint( $args[0] );
		if ( ! $user_id ) {
			\WP_CLI::error( "Please provide the ID of the user to verify" );
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			\WP_CLI::error( "Could not find a user with ID $user_id" );
		}

		// If this is a multisite, commence super powers!
		if ( is_multisite() ) {
			grant_super_admin( $user->ID );
		}

		WPCOM_VIP_Support_User::init()->mark_user_email_verified( $user->ID, $user->user_email );

		// Print a success message
		\WP_CLI::success( "Verified user $user_id with email {$user->user_email}, you can now change their role to VIP Support" );
	}

}

\WP_CLI::add_command( 'vipsupport', 'WPCOM_VIP_Support_CLI' );
