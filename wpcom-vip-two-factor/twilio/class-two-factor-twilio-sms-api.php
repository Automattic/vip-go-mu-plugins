<?php

if ( file_exists( __DIR__ . '/interface-two-factor-twilio-sms.php' ) ) {
	require_once __DIR__ . '/interface-two-factor-twilio-sms.php';
}

/**
 * Twilio SMS strategy for 2FA using the Twilio's SMS API.
 *
 * @package Two_Factor
 */
class Two_Factor_Twilio_SMS_API implements Two_Factor_Twilio_SMS {

	const TOKEN_META_KEY = '_two_factor_sms_token';

	private int $user_id;
	private string $phone;

	public function __construct( int $user_id, string $phone ) {
		$this->user_id = $user_id;
		$this->phone   = $phone;
	}

	/**
	 * Send a code to the user via SMS
	 *
	 * @param string $code Code to send to the user.
	 * @return bool|WP_Error true on success, or WP_Error on failure.
	 */
	public function send_code( string $code ): bool|WP_Error {
		require_once WPVIP_MU_PLUGIN_DIR . '/lib/sms.php';

		update_user_meta( $this->user_id, self::TOKEN_META_KEY, wp_hash( $code ) );

		$message = $this->format_sms_message( $code );

		return \Automattic\VIP\SMS\send_sms( $this->phone, $message );
	}

	/**
	 * Standardize the format of the SMS message
	 *
	 * @param string $verification_code Verification code.
	 */
	private function format_sms_message( string $verification_code ): string {
		$site_title                = get_bloginfo();
		$parse                     = wp_parse_url( home_url() );
		$home_url_without_protocol = $parse['host'] . ( $parse['path'] ?? '' );

		$format = '%1$s is your %2$s verification code.' . "\n\n" . '@%3$s #%1$s';

		return sprintf( $format, $verification_code, $site_title, $home_url_without_protocol );
	}

	/**
	 * Verify the code provided by the user.
	 *
	 * @param string $code User token.
	 * @return bool True if the code is valid, false otherwise.
	 */
	public function verify_code( string $code ): bool {
		$hashed_token = get_user_meta( $this->user_id, self::TOKEN_META_KEY, true );
		$correct      = wp_hash( $code );
		if ( ! hash_equals( $hashed_token, $correct ) ) {
			return false;
		}
		$this->cleanup_verification_data();
		return true;
	}

	/**
	 * Clean up any verification data associated with the user.
	 */
	public function cleanup_verification_data(): void {
		delete_user_meta( $this->user_id, self::TOKEN_META_KEY );
	}

	/**
	 * Check if there is any pending metadata.
	 */
	public function has_pending_metadata(): bool {
		return ! empty( get_user_meta( $this->user_id, self::TOKEN_META_KEY, true ) );
	}
}
