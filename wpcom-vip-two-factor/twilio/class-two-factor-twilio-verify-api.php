<?php

if ( file_exists( __DIR__ . '/interface-two-factor-twilio-sms.php' ) ) {
	require_once __DIR__ . '/interface-two-factor-twilio-sms.php';
}

/**
 * Twilio SMS strategy for 2FA using Twilio's Verify API.
 *
 * @package Two_Factor
 */
class Two_Factor_Twilio_Verify_API implements Two_Factor_Twilio_SMS {

	const VERIFICATION_SID_META_KEY = '_two_factor_twilio_verification_sid';

	const TWILIO_VERIFY_FRIENDLY_NAME_MAX_LENGTH = 30;

	const TWILIO_VERIFY_BASE_URL = 'https://verify.twilio.com/v2';

	private int $user_id;
	private string $phone;

	public function __construct( int $user_id, string $phone ) {
		$this->user_id = $user_id;
		$this->phone   = str_starts_with( $phone, '+' ) ? $phone : '+' . $phone;
	}

	/**
	 * Check if the Twilio Verify API is available.
	 */
	public static function is_available(): bool {
		return defined( 'TWILIO_SID' ) && defined( 'TWILIO_SECRET' ) && defined( 'VIP_TWILIO_VERIFY_SERVICE_SID' );
	}

	/**
	 * Send a code to the user via SMS
	 *
	 * @param string $code Code to send to the user.
	 * @return bool|WP_Error true on success, or WP_Error on failure.
	 */
	public function send_code( string $code ): bool|WP_Error {
		$parse                     = wp_parse_url( home_url() );
		$home_url_without_protocol = $parse['host'] . ( $parse['path'] ?? '' );

		$endpoint = sprintf( '%s/Services/%s/Verifications', self::TWILIO_VERIFY_BASE_URL, VIP_TWILIO_VERIFY_SERVICE_SID );

		$body = array(
			'To'                 => $this->phone,
			'Channel'            => 'sms',
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			'CustomFriendlyName' => $this->get_friendly_name( $home_url_without_protocol ),
			'Tags'               => wp_json_encode( [
				'blog_id'        => get_current_blog_id(),
				'domain'         => $home_url_without_protocol,
				'environment_id' => VIP_GO_APP_ID,
				'user_id'        => $this->user_id,
			] ),
		);

		$response = wp_remote_post( $endpoint, array(
			'body'    => $body,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( TWILIO_SID . ':' . TWILIO_SECRET ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			$masked_number = substr( $this->phone, 0, ( (int) strlen( $this->phone ) / 1.5 ) ) . 'xxx';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'Failed to send SMS to %s: %s #vip-go-sms-error', esc_html( $masked_number ), esc_html( $response->get_error_message() ) ), E_USER_WARNING );

			return new WP_Error( 'twilio_verify_failed', sprintf(
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				__( 'Failed to send verification code to user %1$d: %2$s', 'two-factor' ), $this->user_id,
				$response->get_error_message()
			) );
		}

		$body_raw  = wp_remote_retrieve_body( $response );
		$body_data = json_decode( $body_raw );

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 300 ) {
			$masked_number = substr( $this->phone, 0, ( (int) strlen( $this->phone ) / 1.5 ) ) . 'xxx';

			if ( $body_data && isset( $body_data->code ) && isset( $body_data->message ) ) {
				$error_message = sprintf( 'Twilio Verify API responded with error (%d): %s', $body_data->code, $body_data->message );
			} else {
				$error_message = sprintf( 'Twilio Verify API responded with HTTP response code: %d', $status_code );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'Failed to send SMS to %s: %s #vip-go-sms-error', esc_html( $masked_number ), esc_html( $error_message ) ), E_USER_WARNING );

			return new WP_Error( 'twilio_verify_failed', sprintf(
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				__( 'Failed to send verification code to user %1$d: %2$s', 'two-factor' ), $this->user_id,
				$error_message
			) );
		}

		if ( $body_data && isset( $body_data->sid ) ) {
			update_user_meta( $this->user_id, self::VERIFICATION_SID_META_KEY, $body_data->sid );
		} else {
			return new WP_Error( 'twilio_verify_failed', sprintf(
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				__( 'Failed to send verification code to user %1$d: Invalid response from Twilio', 'two-factor' ), $this->user_id
			) );
		}

		return true;
	}

	/**
	 * Verify the code provided by the user.
	 *
	 * @param string $code User token.
	 * @return bool True if the code is valid, false otherwise.
	 */
	public function verify_code( string $code ): bool {
		$verification_sid = get_user_meta( $this->user_id, self::VERIFICATION_SID_META_KEY, true );

		if ( empty( $verification_sid ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Could not find a Twilio Verify SID for user %d to verify the 2FA code ', $this->user_id, $verification_sid ) );
			return false;
		}

		$endpoint = sprintf( '%s/Services/%s/VerificationCheck', self::TWILIO_VERIFY_BASE_URL, VIP_TWILIO_VERIFY_SERVICE_SID );

		$body = array(
			'VerificationSid' => $verification_sid,
			'Code'            => $code,
		);

		$response = wp_remote_post( $endpoint, array(
			'body'    => $body,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( TWILIO_SID . ':' . TWILIO_SECRET ),
			),
		) );

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Twilio Verify validation request failed for user %d and sid %s: %s',
				$this->user_id,
				$verification_sid,
				$response->get_error_message()
			) );

			$this->cleanup_verification_data();
			return false;
		}

		$body_raw  = wp_remote_retrieve_body( $response );
		$body_data = json_decode( $body_raw );

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 300 ) {
			$error_message = 'Unknown error';
			if ( $body_data && isset( $body_data->code ) && isset( $body_data->message ) ) {
				$error_message = sprintf( 'Twilio Verify API responded with error (%d): %s', $body_data->code, $body_data->message );
			} else {
				$error_message = sprintf( 'Twilio Verify API responded with HTTP response code: %d', $status_code );
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Twilio Verify validation request failed for user %d and sid %s: %s',
				$this->user_id,
				$verification_sid,
				$error_message
			) );

			$this->cleanup_verification_data();
			return false;
		}

		if ( $body_data && isset( $body_data->status ) && 'approved' === $body_data->status ) {
			$this->cleanup_verification_data();
			return true;
		}

		return false;
	}

	/**
	 * Clean up any verification data associated with the user.
	 */
	public function cleanup_verification_data(): void {
		delete_user_meta( $this->user_id, self::VERIFICATION_SID_META_KEY );
	}

	/**
	 * Check if the user has pending metadata.
	 */
	public function has_pending_metadata(): bool {
		return $this->get_verification_sid() !== null;
	}

	private function get_verification_sid(): string|null {
		$verification_sid = get_user_meta( $this->user_id, self::VERIFICATION_SID_META_KEY, true );
		return empty( $verification_sid ) ? null : $verification_sid;
	}

	private function get_friendly_name( string $domain ): string {
		$max_length = $this::TWILIO_VERIFY_FRIENDLY_NAME_MAX_LENGTH;
		$domain     = preg_replace( '/^www\\./', '', $domain );

		if ( strlen( $domain ) <= $max_length ) {
			return $domain;
		}

		// If the domain has at least 3 parts, return it
		$partial = substr( $domain, max( 0, strlen( $domain ) - $max_length ), $max_length );
		$partial = substr( $partial, strpos( $partial, '.' ) + 1 );
		if ( substr_count( $partial, '.' ) > 2 ) {
			return $partial;
		}

		// Returns firstpart[...]secondpart.com limited to TWILIO_VERIFY_FRIENDLY_NAME_MAX_LENGTH
		$ellipsis           = '[...]';
		$first_part_length  = floor( ( $max_length - strlen( $ellipsis ) ) / 2 );
		$second_part_length = $max_length - strlen( $ellipsis ) - $first_part_length;
		return substr( $domain, 0, $first_part_length ) . $ellipsis . substr( $domain, -$second_part_length );
	}
}
