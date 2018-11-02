<?php

/*
Plugin Name: VIP Mail
Description: Routes mail via Automattic mail servers
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

class VIP_NoOp_Mailer {
	function __construct( $phpmailer ) {
		$this->Subject = $phpmailer->Subject;
	}

	function send() {
		trigger_error( sprintf( 'No-Op wp_mail: %s', $this->Subject ?? '' ) );
	}
}

class VIP_SMTP {
	function init() {
		add_action( 'phpmailer_init',    array( $this, 'phpmailer_init' ) );
		add_action( 'bp_phpmailer_init', array( $this, 'phpmailer_init' ) );

		add_filter( 'wp_mail_from', array( $this, 'filter_wp_mail_from' ), 1 );
	}

	function phpmailer_init( $phpmailer ) {
		if ( defined( 'VIP_BLOCK_WP_MAIL' ) && VIP_BLOCK_WP_MAIL ) {
			$phpmailer = new VIP_NoOp_Mailer( $phpmailer );
			return;
		}

		global $all_smtp_servers;

		if ( ! is_array( $all_smtp_servers ) || empty( $all_smtp_servers ) ) {
			return;
		}

		if ( count( $all_smtp_servers ) > 1 ) {
			shuffle( $all_smtp_servers );
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = current( $all_smtp_servers );

		$tracking_header = $this->get_tracking_header( WPCOM_VIP_MAIL_TRACKING_KEY );
		if ( false !== $tracking_header ) {
			$phpmailer->AddCustomHeader( $tracking_header );
		}
	}

	public function filter_wp_mail_from( $from ) {
		return 'donotreply@wordpress.com';
	}

	protected function get_tracking_header( $key ) {
		// Don't need an environment check, since this should never trigger locally
		if ( false === $key ) {
			error_log( sprintf( '%s: Empty tracking header key; check that `WPCOM_VIP_MAIL_TRACKING_KEY` is correctly defined.', __METHOD__ ) );
			return false;
		}

		$caller = $this->get_mail_caller();

		$server_name = php_uname( 'n' );
		$secret_data = [ $caller, FILES_CLIENT_SITE_ID, $server_name ];
		$raw_data = implode( '|', $secret_data );

		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
		$encrypted_caller_and_data = sprintf(
			'%s.%s',
			base64_encode( $iv ),
			openssl_encrypt( $raw_data, 'AES-256-CBC', base64_decode( $key ), 0, $iv )
		);

		$project_id = 1; // Specific to VIP Go
		$site_id = get_current_network_id();
		$blog_id = get_current_blog_id();
		$post_id = get_the_ID();
		$user_id = get_current_user_id();

		return sprintf(
			'X-Automattic-Tracking: %d:%d:%s:%d:%d:%d',
			$project_id,
			$site_id,
			$encrypted_caller_and_data,
			$blog_id,
			$post_id,
			$user_id
		);
	}

	/**
	 * Track down which function/method triggered the email.
	 */
	protected function get_mail_caller() {
		$caller = 'unknown';

		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		foreach ( $trace as $index => $call ) {
			$skip_functions = [
				'do_action',
				'apply_filters',
				'do_action_ref_array',
				'wp_mail',
			];
			if ( in_array( $call['function'], $skip_functions, true ) ) {
				continue;
			}

			if ( isset( $call['class'] ) ) {
				if ( 'VIP_SMTP' === $call['class'] ) {
					continue;
				}

				$caller = sprintf( '%s%s%s', $call['class'], $call['type'] ?? '->', $call['function'] );
				break;
			}

			$caller = $call['function'];
			break;
		}

		return $caller;
	}
}

( new VIP_SMTP() )->init();
