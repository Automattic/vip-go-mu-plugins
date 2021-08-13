<?php

namespace Automattic\VIP\CLI;

use Automattic\VIP\Helpers\User_Cleanup;
use WP_CLI;

class VIP_User_CLI_Command extends \WP_CLI_Command {

	/**
	 * Remove access for a user from an environment.
	 *
	 * Allows lookup via email.
	 *
	 * ## OPTIONS
	 *
	 * --emails=<emails>
	 * : Comma-separated list of emails
	 *
	 * [--yes]
	 * : Remove without confirmation.
	 *
	 * ## EXAMPLES
	 *
	 * wp vip user cleanup --emails="user@example.com,another@example.net"
	 */
	public function cleanup( $args, $assoc_args ) {
		// Allow sites (e.g. Automattic internal) to bypass this cleanup if they have manual cleanup routines.
		$should_do_cleanup = apply_filters( 'vip_do_user_cleanup', true );
		if ( false === $should_do_cleanup ) {
			return \WP_CLI::success( 'Cleanup has been bypassed by the environment.' );
		}

		$emails_arg = \WP_CLI\Utils\get_flag_value( $assoc_args, 'emails' );

		$emails = User_Cleanup::parse_emails_string( $emails_arg );

		if ( empty( $emails ) ) {
			return WP_CLI::error( 'Please provide valid email addresses.' );
		}

		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::line( 'This will remove all access for any users with email addresses that match the following:' );
			foreach ( $emails as $email ) {
				WP_CLI::line( sprintf( '- %s', $email ) );
			}

			WP_CLI::confirm( 'Are you sure?' );
		}

		$user_ids = User_Cleanup::fetch_user_ids_for_emails( $emails );

		if ( empty( $user_ids ) ) {
			return WP_CLI::success( 'No users found for the specified email addresses.' );
		}

		if ( is_multisite() ) {
			return $this->process_multisite( $user_ids );

			// TODO: output
		}

		User_Cleanup::revoke_roles_for_users( $user_ids );

		// TODO: output
	}

	private function process_multisite( $user_ids ) {
		global $wpdb;

		User_Cleanup::revoke_super_admin_for_users( $user_ids );

		// TODO: switch to get_sites() or wpdb?
		$iterator_args = [
			'table' => $wpdb->blogs,
			'where' => [
				'spam' => 0,
				'deleted' => 0,
				'archived' => 0,
			],
			'fields' => [ 'blog_id' ],
		];
		$sites_iterator = new \WP_CLI\Iterators\Table( $iterator_args );

		foreach ( $sites_iterator as $site ) {
			switch_to_blog( $site->blog_id );

			// TODO: output

			User_Cleanup::revoke_roles_for_users( $user_ids );
		}
	}
}

\WP_CLI::add_command( 'vip user', __NAMESPACE__ . '\VIP_User_CLI_Command' );
