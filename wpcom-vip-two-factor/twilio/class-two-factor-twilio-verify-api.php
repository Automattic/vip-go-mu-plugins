<?php

use Twilio\Exceptions\TwilioException;

/**
 * Twilio SMS strategy for 2FA using Twilio's Verify API.
 *
 * @package Two_Factor
 */
class Two_Factor_Twilio_Verify_API implements Two_Factor_Twilio_SMS {

	const VERIFICATION_SID_META_KEY = '_two_factor_twilio_verification_sid';

	const TWILIO_VERIFY_FRIENDLY_NAME_MAX_LENGTH = 30;

	private static Twilio\Rest\Client $twilio_client;

	private int $user_id;
	private string $phone;

	private static function get_client(): Twilio\Rest\Client {
		if ( ! isset( self::$twilio_client ) ) {
			require_once __DIR__ . '/../../twilio-sdk/src/Twilio/autoload.php';
			self::$twilio_client = new Twilio\Rest\Client( TWILIO_SID, TWILIO_SECRET );
		}
		return self::$twilio_client;
	}

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

		try {
			$verification = self::get_client()->verify->v2
				->services( VIP_TWILIO_VERIFY_SERVICE_SID )
				->verifications
				->create( $this->phone, 'sms', [
                    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					// 'customFriendlyName' => $this->get_friendly_name( $home_url_without_protocol ), // FIXME: Use once Twilio enables Custom Friendly Names
					'tags' => wp_json_encode([
						'blog_id'        => get_current_blog_id(),
						'domain'         => $home_url_without_protocol,
						'environment_id' => VIP_GO_APP_ID,
						'user_id'        => $this->user_id,
					] ),
				] );

			update_user_meta( $this->user_id, self::VERIFICATION_SID_META_KEY, $verification->sid );
		} catch ( TwilioException $e ) {

			$masked_number = substr( $this->phone, 0, ( (int) strlen( $this->phone ) / 1.5 ) ) . 'xxx';
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'Failed to send SMS to %s: %s %s #vip-go-sms-error', esc_html( $masked_number ), esc_html( $e->getCode() ), esc_html( $e->getMessage() ) ), E_USER_WARNING );

			return new WP_Error( 'twilio_verify_failed', sprintf(
				// phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				__( 'Failed to send verification code to user %1$d: %2$s', 'two-factor' ), $this->user_id,
				$e->getMessage()
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

		try {
			$verification_check = self::get_client()->verify->v2
				->services( VIP_TWILIO_VERIFY_SERVICE_SID )
				->verificationChecks->create([
					'verificationSid' => $verification_sid,
					'code'            => $code,
				]);

			if ( 'approved' === $verification_check->status ) {
				$this->cleanup_verification_data();
				return true;
			}

			return false;
		} catch ( TwilioException $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf('Twilio Verify validation request failed for user %d and sid %s: %s',
				$this->user_id,
				$verification_sid,
				$this->get_message_from_twilio_exception( $e )
			) );

			$this->cleanup_verification_data();

			return false;
		}
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

	private function get_message_from_twilio_exception( TwilioException $e ): string {
		return sprintf( 'Twilio Verify Client failed with error (%s): %s', $e->getCode(), $e->getMessage() );
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
