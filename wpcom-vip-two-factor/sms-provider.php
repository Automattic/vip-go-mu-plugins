<?php

/**
 * Class for creating an sms provider.
 *
 * @package Two_Factor
 */
class Two_Factor_SMS extends Two_Factor_Provider {

	/**
	 * The user meta token key.
	 *
	 * @type string
	 */
	const TOKEN_META_KEY = '_two_factor_sms_token';

	const PHONE_META_KEY = '_vip_two_factor_phone';

	static function get_instance() {
		static $instance;
		$class = __CLASS__;
		if ( ! is_a( $instance, $class ) ) {
			$instance = new $class;
		}
		return $instance;
	}

	protected function __construct() {
		add_action( 'two-factor-user-options-' . __CLASS__, array( $this, 'user_options' ) );
		add_action( 'personal_options_update', array( $this, 'user_options_update' ) );
		add_action( 'edit_user_profile_update', array( $this, 'user_options_update' ) );
		return parent::__construct();
	}

	/**
	 * Returns the name of the provider.
	 */
	public function get_label() {
		return _x( 'SMS', 'Provider Label', 'two-factor' );
	}

	/**
	 * Generate the user token.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function generate_token( $user_id ) {
		$token = $this->get_code();
		update_user_meta( $user_id, self::TOKEN_META_KEY, wp_hash( $token ) );
		return $token;
	}

	/**
	 * Validate the user token.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token User token.
	 * @return boolean
	 */
	public function validate_token( $user_id, $token ) {
		$hashed_token = get_user_meta( $user_id, self::TOKEN_META_KEY, true );
		$correct = wp_hash( $token );
		if ( ! hash_equals( $hashed_token, $correct ) ) {
			return false;
		}
		$this->delete_token( $user_id );
		return true;
	}

	/**
	 * Delete the user token.
	 *
	 * @param int $user_id User ID.
	 */
	public function delete_token( $user_id ) {
		delete_user_meta( $user_id, self::TOKEN_META_KEY );
	}

	/**
	 * Generate and send the user token.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function generate_and_send_token( $user ) {
		require_once( WPMU_PLUGIN_DIR . '/lib/sms.php' );
		$code = $this->generate_token( $user->ID );
		$sms = get_user_meta( $user->ID, self::PHONE_META_KEY, true );
			return \Automattic\VIP\SMS\send_sms( $sms, $code );
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

			if ( ! isset( $_GET['action'] ) || 'validate_2fa' !== $_GET['action'] ) {
				$this->generate_and_send_token( $user );
			}

			// Including template.php for submit_button()
			require_once( ABSPATH .  '/wp-admin/includes/template.php' );
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
		if ( ! isset( $user->ID ) || ! isset( $_REQUEST['two-factor-sms-code'] ) ) {
			return false;
		}

		return $this->validate_token( $user->ID, $_REQUEST['two-factor-sms-code'] );
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
		$sms = get_user_meta( $user->ID, self::PHONE_META_KEY, true );
		wp_nonce_field( 'user_two_factor_sms_options', '_nonce_user_two_factor_sms_options', false );
		?>
		<div>
			<input name="vip-two-factor-phone" type="tel" placeholder="+14158675309" value="<?php echo $sms ;?>" />
		</div>
		<?php
	}

	public function user_options_update( $user_id ) {
		if ( ! isset( $_POST['_nonce_user_two_factor_sms_options'] ) ) {
			return;
		}

		check_admin_referer( 'user_two_factor_sms_options', '_nonce_user_two_factor_sms_options' );

		if ( ! isset( $_POST[ 'vip-two-factor-phone' ] ) ) {
			return;
		}

		return update_user_meta( $user_id, self::PHONE_META_KEY, $_POST[ 'vip-two-factor-phone' ] );
	}
}
