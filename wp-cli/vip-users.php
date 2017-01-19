<?php
class VIP_Go_Users_Command extends WPCOM_VIP_CLI_Command {
	/**
	 * Changes the WordPress username (user_login)
	 *
	 * Replicates functionality from WordPress.com's NameChange::rename_user()
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The existing username of the user to rename.
	 *
	 * <newname>
	 * : The new username to use.
	 *
	 * eg.: `wp vip-go-users change-username jsmith johns`
	 *
	 * @subcommand change-username
	 */
	public function change_username( $args, $assoc_args ) {
		list( $username, $newname ) = $args;

		$current_user = get_user_by( 'login', $username );

		if ( false === $current_user ) {
			WP_CLI::error( 'User "' . $username . '" does not exist!' );
		}

		global $wpdb;

		$updates = $wpdb->prepare( ', user_nicename = %s', $newname );

		if ( $username === $current_user->display_name ) {
			$updates .= $wpdb->prepare( ', display_name = %s', $newname );
		}

		if ( get_user_meta( $current_user->ID, 'nickname', true ) === $username ) {
			update_usermeta( $current_user->ID, 'nickname', $newname );
		}

		// updates the neccessary fields in the sql tables
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->users SET user_login = %s{$updates} WHERE ID = %d", $newname, $current_user->ID ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE jabber_users SET user_login = %s WHERE user_ID = %d', $newname, $current_user->ID ) );

		WP_CLI::log( 'Username "' . $username . '" changed to "' . $newname . '"' );

		// update the neccessary caches
		clean_user_cache( $current_user->ID );
		$new_user = get_user_by( 'login', $newname );
		update_user_caches( $new_user->data );

		do_action( 'log_username_change', $current_user->ID, $username, $newname );

		// creates a new 'deleted' username (so the name is reserved but can't be used later)
		if ( is_multisite() ) {
			$new_user_id = wpmu_create_user( $username, wp_generate_password(), '--deleted-account-' . $username . '-' . $current_user->user_email );
		} else {
			$new_user_id = wp_create_user( $username, wp_generate_password(), '--deleted-account-' . $username . '-' . $current_user->user_email );
		}
		if ( $new_user_id ) {
			wp_update_user( array( 'ID' => $new_user_id, 'deleted' => 1 ) );
			update_user_meta( $new_user_id, 'gravatar-status', 'deleted' );
			WP_CLI::log( 'Old username "' . $username . '" marked deleted.' );
		}

		do_action( 'log_username_change', $new_user_id, $username, $newname );
	}
}
WP_CLI::add_command( 'vip-go-users', 'VIP_Go_Users_Command' );
