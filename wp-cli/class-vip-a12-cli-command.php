<?php

if ( defined( 'VIP_SKIP_A12_CLEANUP' ) && true === VIP_SKIP_A12_CLEANUP ) {
	add_filter( 'vip_a12_do_cleanup', '__return_false' );
}

class A12_Cleanup_Utils {
	public static function parse_emails_string( $emails_string ) {
		$emails = [];

		if ( false !== strpos( $emails_string, ',' ) ) {
			$emails_raw = explode( ',', $emails_string );
		} else {
			$emails_raw = [ $emails_string ];
		}

		foreach( $emails_raw as $email_raw ) {
			$email_raw = trim( $email_raw );

			$email_filtered = filter_var( $email_raw, FILTER_VALIDATE_EMAIL );

			if ( empty( $email_filtered ) ) {
				continue;
			}

			$emails[ $email_filtered ] = explode( '@', $email_filtered );
		}

		return $emails;
	}

	public static function fetch_user_ids_for_emails( $emails ) {
		global $wpdb;

		$email_sql_where_array = [];
		foreach ( $emails as $email => $email_username_hostname_split ) {
			list( $email_username, $email_hosname ) = $email_username_hostname_split;

			// TODO Fix escaping
			$email_sql_where_array[] = $wpdb->prepare(
				"( user_email = %s OR ( user_email LIKE %s AND user_email LIKE %s ) )",
				$email, // search for exact match
				$wpdb->esc_like( $user_email_username . '+' . '%' ), // search for `username+*`
				$wpdb->esc_like( '%' . '@' . $user_email_host ) // search for `*@example.com`
			);
		}

		$email_sql_where = implode( ' OR ', $email_sql_where_array );

		$sql = "SELECT ID AS role
			FROM {$wpdb->users}
			LEFT JOIN {$wpdb->usermeta} ON {$wpdb->users}.ID = {$wpdb->usermeta}.user_id
			WHERE meta_key LIKE 'wp_%%capabilities'
			AND ( {$email_sql_where} )"; // already escaped

		return $wpdb->get_col( $sql );
	}

	public static function revoke_super_admins( $user_ids ) {
		foreach ( $user_ids as $user_id ) {
			if ( is_super_admin( $user_id ) ) {
				$revoked_super_admin = revoke_super_admin( $user_id );

				// TODO: log
			}
		}
	}

	public static function revoke_users_from_current_site( $user_ids ) {
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );

			if ( ! $user ) {
				// TODO: log

				continue;
			}

			$user->remove_all_caps();

			// TODO: log
		}
	}
}

class VIP_A12_CLI_Command extends \WPCOM_VIP_CLI_Command {

	/**
	 * Remove access for a user from an environment.
	 *
	 * Allows lookup via email.
	 *
	 * ## OPTIONS
	 *
	 * [--emails=<emails>]
	 * : comma-separated list of emails
	 */
	public function cleanup( $args, $assoc_args ) {
		// Allow Automattic sites to bypass this cleanup if they have manual cleanup routines
		$should_do_cleanup = apply_filters( 'vip_a12_do_cleanup' , true );
		if ( false === $should_do_cleanup ) {
			return WP_CLI::success( 'Cleanup has been bypassed by the environment' );
		}

		$emails_arg = WP_CLI\Utils\get_flag_value( $assoc_args, 'emails' );

		$emails = A12_Cleanup_Utils::parse_emails_string( $emails_arg );

		if ( empty( $emails ) ) {
			return WP_CLI::error( 'Please provide valid email addresses.' );
		}

		$user_ids = A12_Cleanup_Utils::fetch_user_ids_for_emails( $emails );

		if ( empty( $user_ids ) ) {
			return WP_CLI::success( 'No users found for the specified email addresses.' );
		}

		if ( is_multisite() ) {
			return $this->process_multisite( $user_ids );
		}
				
		A12_Cleanup_Utils::revoke_users_from_current_site( $user_ids );
	}

	private function process_multisite( $user_ids ) {
		A12_Cleanup_Utils::revoke_super_admins( $user_ids );

		$iterator_args = array(
			'table' => $wpdb->blogs,
			'where' => array( 'spam' => 0, 'deleted' => 0, 'archived' => 0 ),
			'fields' => array( 'blog_id' ),
		);
		$iterator = new \WP_CLI\Iterators\Table( $iterator_args );

		foreach( $sites_iterator as $site ) {
			switch_to_blog( $site->blog_id );

			// TODO: log

			A12_Cleanup_Utils::revoke_users_from_current_site( $user_ids );
		}
	}
}

WP_CLI::add_command( 'vip a12', '\VIP_A12_CLI_Command' );