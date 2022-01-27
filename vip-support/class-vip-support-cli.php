<?php
/**
 * CLI commands
 */

namespace Automattic\VIP\Support_User;
use WP_CLI_Command;

/**
 * Implements a WP CLI command that converts guid users to meta users
 * Class command
 *
 * @package a8c\vip_support
 */
class Command extends WP_CLI_Command {

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
		$user_data['first_name'] = ! empty( $display_name ) ? $display_name : $user_login;
		$user_data['last_name'] = '(VIP Support)';
		$user_data['locale'] = 'en_US';

		$user_id = User::add( $user_data );

		if ( is_wp_error( $user_id ) ) {
			\WP_CLI::error( $user_id );
		}

		$msg = "Added user $user_id with login {$user_login}, they are verified as a VIP Support user and ready to go";
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

		$success = User::remove( $user_email );

		if ( is_wp_error( $success ) ) {
			\WP_CLI::error( $success->get_error_message() );
		}

		\WP_CLI::success( "Deleted user with email {$user_email}" );
	}


	/**
	 * Marks the user with the provided ID as having a verified email.
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

		User::init()->mark_user_email_verified( $user->ID, $user->user_email );

		// Print a success message
		\WP_CLI::success( "Verified user $user_id with email {$user->user_email}, you can now change their role to VIP Support" );
	}

	/**
	 * Reset / re-add VIP Support related roles.
	 *
	 * @subcommand reset-roles
	 *
	 * ## EXAMPLES
	 *
	 *     wp vipsupport reset-roles
	 */
	public function reset_roles( $args ) {
		delete_option( 'vipsupportrole_version' );

		Role::init()->maybe_upgrade_version();

		\WP_CLI::success( __( 'VIP Support roles successfully reset' ) );
	}

}

\WP_CLI::add_command( 'vipsupport', __NAMESPACE__ . '\Command' );
