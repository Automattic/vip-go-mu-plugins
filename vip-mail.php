<?php

/*
Plugin Name: VIP Mail
Description: Routes mail via Automattic mail servers
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- needs refactoring
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer does not follow the conventions
namespace Automattic\VIP\Mail;

use PHPMailer\PHPMailer\PHPMailer;

if ( ! class_exists( 'PHPMailer\PHPMailer\PHPMailer' ) ) {
	require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
	require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
	require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
}

class VIP_PHPMailer extends PHPMailer {
	/**
	 * Check whether a file path is of a permitted type.
	 *
	 * Used to reject URLs and phar files from functions that access local file paths,
	 * such as addAttachment. Allows VIP File System's `vip` protocol.
	 *
	 * @param string $path A relative or absolute path to a file
	 *
	 * @return bool
	 */
	protected static function isPermittedPath( $path ) {
		if ( 0 === strpos( $path, 'vip://wp-content/uploads' ) ) {
			return true;
		} else {
			return ! preg_match( '#^[a-z]+://#i', $path );
		}
	}
}

if ( defined( 'USE_VIP_PHPMAILER' ) && true === constant( 'USE_VIP_PHPMAILER' ) ) {
	global $phpmailer;
	$phpmailer = new VIP_PHPMailer( true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}

class VIP_Noop_Mailer {

	/**
	 * @var string
	 */
	public $subject;

	/**
	 * @var string
	 */
	public $recipients;

	public function __construct( $phpmailer ) {
		$this->subject    = $phpmailer->Subject ?? '[No Subject]';
		$this->recipients = implode( ', ', array_keys( $phpmailer->getAllRecipientAddresses() ) );
	}

	public function send() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( sprintf( '%s: skipped sending email with subject `%s` to %s', __METHOD__, esc_html( $this->subject ), esc_html( $this->recipients ) ), E_USER_NOTICE );
	}
}

final class VIP_SMTP {
	private static ?VIP_SMTP $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'phpmailer_init', array( $this, 'phpmailer_init' ), 99 );
		add_action( 'bp_phpmailer_init', array( $this, 'phpmailer_init' ), 99 );

		if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
			add_filter( 'wp_mail_from', array( $this, 'filter_wp_mail_from' ), 1 );
		}
	}

	/**
	 * @param PHPMailer $phpmailer
	 */
	public function phpmailer_init( &$phpmailer ): void {
		if ( defined( 'VIP_BLOCK_WP_MAIL' ) && true === constant( 'VIP_BLOCK_WP_MAIL' ) ) { // Constant will take precedence over filter
			$phpmailer = new VIP_Noop_Mailer( $phpmailer );
			return;
		}

		if ( true === apply_filters( 'vip_block_wp_mail', false ) ) {
			$phpmailer = new VIP_Noop_Mailer( $phpmailer );
			return;
		}

		$host_overwrite_allow_list = defined( 'VIP_SMTP_HOST_OVERWRITE_ALLOW_LIST' )
			? array_map( 'trim', explode( ',', constant( 'VIP_SMTP_HOST_OVERWRITE_ALLOW_LIST' ) ) )
			: [];

		if ( 'smtp' === $phpmailer->Mailer && ! in_array( $phpmailer->Host, $host_overwrite_allow_list, true ) ) {
			return;
		}

		global $all_smtp_servers;

		if ( empty( $all_smtp_servers ) || ! is_array( $all_smtp_servers ) ) {
			return;
		}

		if ( count( $all_smtp_servers ) > 1 ) {
			shuffle( $all_smtp_servers );
		}

		/** @var PHPMailer $phpmailer */

		$phpmailer->isSMTP();
		$phpmailer->Host = current( $all_smtp_servers );

		if ( defined( 'VIP_SMTP_ENABLED' ) && true === constant( 'VIP_SMTP_ENABLED' ) && defined( 'VIP_SMTP_USERNAME' ) && defined( 'VIP_SMTP_PASSWORD' ) ) {
			$phpmailer->Port       = constant( 'VIP_SMTP_PORT' );
			$phpmailer->SMTPAuth   = true;
			$phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			$phpmailer->Username   = constant( 'VIP_SMTP_USERNAME' );
			$phpmailer->Password   = constant( 'VIP_SMTP_PASSWORD' );
		}

		$tracking_header = $this->get_tracking_header( WPCOM_VIP_MAIL_TRACKING_KEY );
		if ( false !== $tracking_header ) {
			$phpmailer->AddCustomHeader( $tracking_header );
		}
	}

	public function filter_wp_mail_from() {
		return 'donotreply@wpvip.com';
	}

	protected function get_tracking_header( $key ) {
		// Don't need an environment check, since this should never trigger locally
		if ( false === $key ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '%s: Empty tracking header key; check that `WPCOM_VIP_MAIL_TRACKING_KEY` is correctly defined.', __METHOD__ ) );
			return false;
		}

		$caller = $this->get_mail_caller();

		$server_name = php_uname( 'n' );
		$secret_data = [ $caller, FILES_CLIENT_SITE_ID, $server_name ];
		$raw_data    = implode( '|', $secret_data );

		$iv                        = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
		$encrypted_caller_and_data = sprintf(
			'%s.%s',
			base64_encode( $iv ),
			openssl_encrypt( $raw_data, 'AES-256-CBC', base64_decode( $key ), 0, $iv )
		);

		$project_id = 1; // Specific to VIP Go
		$site_id    = get_current_network_id();
		$blog_id    = get_current_blog_id();
		$post_id    = get_the_ID();
		$user_id    = get_current_user_id();

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

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		foreach ( $trace as $call ) {
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

VIP_SMTP::instance();
