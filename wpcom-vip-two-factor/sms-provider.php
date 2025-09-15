<?php

if ( file_exists( __DIR__ . '/twilio/interface-two-factor-twilio-sms.php' ) ) {
	require_once __DIR__ . '/twilio/interface-two-factor-twilio-sms.php';
}
if ( file_exists( __DIR__ . '/twilio/class-two-factor-twilio-sms-api.php' ) ) {
	require_once __DIR__ . '/twilio/class-two-factor-twilio-sms-api.php';
}
if ( file_exists( __DIR__ . '/twilio/class-two-factor-twilio-verify-api.php' ) ) {
	require_once __DIR__ . '/twilio/class-two-factor-twilio-verify-api.php';
}

/**
 * Class for creating an sms provider.
 *
 * @package Two_Factor
 */
class Two_Factor_SMS extends Two_Factor_Provider {

	const PHONE_META_KEY = '_vip_two_factor_phone';

	const SMS_CONFIGURED_META_KEY = '_vip_two_factor_sms_configured';

	const QATAR_PHONE_REGEX = '/^\+?974[3-7]\d{7}$/';

	public static function get_instance() {
		static $instance;
		$class = __CLASS__;
		if ( ! is_a( $instance, $class ) ) {
			$instance = new $class();
		}
		return $instance;
	}

	protected function __construct() {
		add_action( 'two_factor_user_options_' . __CLASS__, array( $this, 'user_options' ) );
		add_action( 'personal_options_update', array( $this, 'user_options_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_options_update' ) );
		parent::__construct();
	}

	/**
	 * @param int $user_id
	 * @return Two_Factor_Twilio_SMS
	 */
	public function get_sms_strategy( int $user_id ): Two_Factor_Twilio_SMS {
		$phone = get_user_meta( $user_id, self::PHONE_META_KEY, true );

		if ( Two_Factor_Twilio_Verify_API::is_available() ) {
			if ( preg_match( self::QATAR_PHONE_REGEX, $phone ) ) {
				return new Two_Factor_Twilio_Verify_API( $user_id, $phone );
			}
		}

		return new Two_Factor_Twilio_SMS_API( $user_id, $phone );
	}

	/**
	 * Returns the name of the provider.
	 */
	public function get_label() {
		return _x( 'SMS', 'Provider Label', 'two-factor' );
	}

	/**
	 * Generate and send the user token.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function generate_and_send_token( $user ) {
		$strategy = $this->get_sms_strategy( $user->ID );
		$result   = $strategy->send_code( $this->get_code() );

		if ( is_wp_error( $result ) ) {
			$strategy->cleanup_verification_data();
			return new WP_Error( 'verification_failed', __( 'Failed to send verification code.', 'two-factor' ) );
		}
	}

	/**
	 * Prints the form that prompts the user to authenticate.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function authentication_page( $user ) {
		if ( ! $user ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is not available
		$action = sanitize_key( $_GET['action'] ?? '' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is not available
		$provider = sanitize_text_field( $_GET['provider'] ?? '' );
		$method   = sanitize_text_field( $_SERVER['REQUEST_METHOD'] ?? '' );

		if ( ( 'POST' === $method && 'validate_2fa' !== $action ) || ( 'GET' === $method || 'Two_Factor_SMS' === $provider ) ) {
			$this->generate_and_send_token( $user );
		}

		// Including template.php for submit_button()
		require_once ABSPATH . '/wp-admin/includes/template.php';
		?>
		<p><?php esc_html_e( 'A verification code has been sent to the phone number associated with your account.', 'two-factor' ); ?></p>
	<p>
		<label for="authcode"><?php esc_html_e( 'Verification Code:', 'two-factor' ); ?></label>
		<input type="tel" name="two-factor-sms-code" id="authcode" class="input" value="" size="20" pattern="[0-9]*" />
	</p>
		<?php
		submit_button( __( 'Log In', 'two-factor' ) );
	}

	/**
	 * Validates the users input token.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return boolean
	 */
	public function validate_authentication( $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is not available
		if ( ! isset( $user->ID ) || ! isset( $_REQUEST['two-factor-sms-code'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		return $this->get_sms_strategy( $user->ID )->verify_code( $_REQUEST['two-factor-sms-code'] );
	}

	/**
	 * Whether this Two Factor provider is configured and available for the user specified.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 * @return boolean
	 */
	public function is_available_for_user( $user ) {
		$sms = get_user_meta( $user->ID, self::PHONE_META_KEY, true );
		return ! empty( $sms );
	}

	/**
	 * Inserts markup at the end of the user profile field for this provider.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function user_options( $user ) {
		$sms            = get_user_meta( $user->ID, self::PHONE_META_KEY, true );
		$is_pending     = $this->get_sms_strategy( $user->ID )->has_pending_metadata();
		$sms_configured = get_user_meta( $user->ID, self::SMS_CONFIGURED_META_KEY, true );
		wp_nonce_field( 'user_two_factor_sms_options', '_nonce_user_two_factor_sms_options', false );
		?>
		<div>
			<?php if ( '1' === $sms_configured ) : ?>
				Correctly configured for <?php echo esc_attr( $sms ); ?>.
				<input type="submit" class="button" name="vip-two-factor-phone-delete"
					value="<?php esc_attr_e( 'Delete', 'two-factor' ); ?>"/>
			<?php elseif ( $is_pending ) : ?>
				<p>
					Verification code has been sent to <?php echo esc_attr( $sms ); ?>
					<input type="submit" class="button" name="vip-two-factor-phone-send-code"
						value="<?php esc_attr_e( 'Resend', 'two-factor' ); ?>"/>
					<input type="submit" class="button" name="vip-two-factor-phone-delete"
						value="<?php esc_attr_e( 'Delete', 'two-factor' ); ?>"/>
				</p>
				<label>Verification Code:
					<input name="two-factor-sms-code"/>
				</label>
				<input type="submit" class="button" name="vip-two-factor-phone-verify-code"
					value="<?php esc_attr_e( 'Submit', 'two-factor' ); ?>"/>
			<?php else : ?>
				<label>Phone Number
					<input name="vip-two-factor-phone" type="tel"
						value="<?php echo esc_attr( $sms ); ?>"/>
				</label>
				<input type="submit" class="button" name="vip-two-factor-phone-send-code"
					value="<?php esc_attr_e( 'Submit', 'two-factor' ); ?>"/>
				<p><strong>Note:</strong> Please include your country calling code (e.g. +44, +1, +61, etc.) to ensure
					SMS messages are correctly sent.</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function user_options_update( $user_id ) {
		if ( ! isset( $_POST['_nonce_user_two_factor_sms_options'] ) ) {
			return;
		}
		check_admin_referer( 'user_two_factor_sms_options', '_nonce_user_two_factor_sms_options' );

		if ( isset( $_POST['vip-two-factor-phone-verify-code'] ) ) {
			$user = get_userdata( $user_id );
			if ( $this->validate_authentication( $user ) ) {
				update_user_meta( $user_id, self::SMS_CONFIGURED_META_KEY, 1 );
			}
		}

		if ( isset( $_POST['vip-two-factor-phone-delete'] ) ) {
			delete_user_meta( $user_id, self::SMS_CONFIGURED_META_KEY );
			delete_user_meta( $user_id, self::PHONE_META_KEY );
			$this->get_sms_strategy( $user_id )->cleanup_verification_data();
		}

		if ( isset( $_POST['vip-two-factor-phone'] ) ) {
			// Remove all characters except digits and +-
			$sms = filter_var( $_POST['vip-two-factor-phone'], FILTER_SANITIZE_NUMBER_FLOAT );
			update_user_meta( $user_id, self::PHONE_META_KEY, $sms );
		}

		if ( isset( $_POST['vip-two-factor-phone-send-code'] ) ) {
			$user = get_userdata( $user_id );
			$this->generate_and_send_token( $user );
		}
	}
}
