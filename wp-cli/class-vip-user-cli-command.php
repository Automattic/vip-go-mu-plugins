<?php

namespace Automattic\VIP\CLI;

use Automattic\VIP\Helpers\User_Cleanup;
use WP_CLI;
use WPCOM_VIP_CLI_Command;

class VIP_User_CLI_Command extends WPCOM_VIP_CLI_Command {

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
		$emails_arg = \WP_CLI\Utils\get_flag_value( $assoc_args, 'emails' );

		$emails = User_Cleanup::parse_emails_string( $emails_arg );

		if ( empty( $emails ) ) {
			WP_CLI::error( 'Please provide valid email addresses.' );
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
			WP_CLI::success( 'No users found for the specified email addresses.' );
			return;
		}

		if ( is_multisite() ) {
			$this->process_multisite( $user_ids );
			return;
		}

		$this->do_site_removals( $user_ids );
	}

	private function process_multisite( $user_ids ) {
		global $wpdb;

		$this->do_super_admin_removals( $user_ids );

		// TODO: switch to get_sites() or wpdb?
		$iterator_args  = [
			'table'  => $wpdb->blogs,
			'where'  => [
				'spam'     => 0,
				'deleted'  => 0,
				'archived' => 0,
			],
			'fields' => [ 'blog_id' ],
		];
		$sites_iterator = new \WP_CLI\Iterators\Table( $iterator_args );

		foreach ( $sites_iterator as $site ) {
			switch_to_blog( $site->blog_id );

			$this->do_site_removals( $user_ids );
		}
	}

	private function do_super_admin_removals( $user_ids ) {
		WP_CLI::line( '== Processing Super Admins ==' );

		$super_admin_removals = User_Cleanup::revoke_super_admin_for_users( $user_ids );
		$this->output_super_admin_removal_results( $super_admin_removals );
	}

	private function do_site_removals( $user_ids ) {
		WP_CLI::line( sprintf( '== Processing site %s (%d) ==', home_url(), get_current_blog_id() ) );
		$site_removals = User_Cleanup::revoke_roles_for_users( $user_ids );
		$this->output_site_removal_results( $site_removals );
	}

	private function output_super_admin_removal_results( $super_admin_removals ) {
		foreach ( $super_admin_removals as $user_id => $revoked ) {
			$user = get_userdata( $user_id );
			WP_CLI::line( sprintf( '- %s (%d)', $user->user_login, $user_id ) );
		}
	}

	private function output_site_removal_results( $site_removals ) {
		foreach ( $site_removals as $user_id => $revoked ) {
			$user = get_userdata( $user_id );
			WP_CLI::line( sprintf( '- %s (%d)', $user->user_login, $user_id ) );
		}
	}
}

\WP_CLI::add_command( 'vip user', __NAMESPACE__ . '\VIP_User_CLI_Command' );
