<?php

// Bail if we don't have Twilio credentials
if ( ! defined( 'TWILIO_SID' ) || ! defined( 'TWILIO_SECRET' ) ) {
	return;
}

/**
 * Class for creating an sms provider.
 *
 * @package Two_Factor
 */
class Two_Factor_SMS extends Two_Factor_Provider {

	/**
	 * The base API endpoint
	 *
	 * @type string
	 */
	const TWILIO_BASE_URL = 'https://api.twilio.com/2010-04-01';

	/**
	 * Twilio Account to send from
	 *
	 * @type string
	 */
	const TWILIO_ACCOUNT = 'ACe16d3eaebadd491f285297e03b4d3234';

	/**
	 * Phone number to send SMS from
	 *
	 * @type string
	 */
	const FROM_NUMBER = '+14159695849';

	/**
	 * The maximum SMS length
	 *
	 * @type int
	 */
	const MAX_SMS_LENGTH = 140;

	/**
	 * The user meta token key.
	 *
	 * @type string
	 */
	const TOKEN_META_KEY = '_two_factor_sms_token';

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
		add_filter( 'user_contactmethods', array( $this, 'user_contactmethods' ) );
		return parent::__construct();
	}

	public function user_contactmethods( $methods ) {
		$methods['phone'] = _x( 'Phone', 'Phone Label', 'two-factor' );
		return $methods;
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
		if ( wp_hash( $token ) !== $hashed_token ) {
			$this->delete_token( $user_id );
			return false;
		}
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
		$code = $this->generate_token( $user->ID );
		return self::send_sms( $user->phone, $code );
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

		$this->generate_and_send_token( $user );
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
		return ! empty( $user->phone );
	}

	/**
	 * Inserts markup at the end of the user profile field for this provider.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function user_options( $user ) {
		$sms = $user->phone;
		?>
		<div>
			<?php
			echo esc_html( sprintf(
				/* translators: %s: sms address */
				__( 'Authentication codes will be sent to %s.', 'two-factor' ),
				$sms
			) );
			?>
		</div>
		<?php
	}

	/**
	 * Send an SMS message to the specified numbers using the Twilio API
	 *
	 * @param int|array Nnumber(s) to send an SMS to. Accepts formatted and unformatted US numbers, e.g. +14155551212, (415) 555-1212 or 415-555-1212.
	 * @param string Message to send. Longer than 140 chars will be split.
	 *
	 * @link http://www.twilio.com/docs/api/rest/sending-sms
	 */
	static function send_sms( $to, $message, $country_code = '+1' ) {

		$counter_pattern = ' (%s of %s)';
		$counter_length = strlen( $counter_pattern ); // (01 of 02)

		// Split message if > 140, otherwise Twilio freaks out
		if ( self::MAX_SMS_LENGTH < strlen( $message ) ) {
			$message = self::split_words( $message, self::MAX_SMS_LENGTH - $counter_length );
		}
		// Cast as an array, just in case
		$message = (array) $message;
		$to = (array) $to;

		foreach ( $to as $to_number ) {
			$counter = 0;
			$total_messages = count( $message );

			foreach ( $message as $message_split ) {
				$counter++;

				// Add a counter if we're sending more than one message
				if ( 1 < $total_messages )
					$message_split .= sprintf( $counter_pattern, $counter, $total_messages );

				$body = array(
					'From' => self::FROM_NUMBER,
					'To' => $to_number,
					'Body' => $message_split,
				);

				self::send_single_sms_via_rest( $body );
			}
		}
	}

	static function send_single_sms_via_rest( $body ) {
		$endpoint = self::get_rest_url( 'Messages.json' );
		$sent = wp_remote_post( $endpoint, array(
			'body' => $body,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( TWILIO_SID . ':' . TWILIO_SECRET ), // {AccountSid}:{AuthToken}
			)
		) );
	}

	// action should be something like SMS/Messages.json or Calls.json
	static function get_rest_url( $action ) {
		return sprintf( '%s/Accounts/%s/%s', self::TWILIO_BASE_URL, self::TWILIO_ACCOUNT, $action );
	}

	/**
	 * Split string into group of lines containing only words,
	 * having maximum line length $max characters
	 *
	 * @param string $longtext
	 * @param integer $max,
	 * @return array - lines of words with $max line length
	 *
	 * @author Daniel Petrovic
	 * @link http://www.php.net/manual/en/function.preg-split.php#108090
	 */
	static function split_words($longtext, $max = 1) {
		// spaces or commas are not considered to be words
		// between '[' and ']' can be put all characters considered to be
		// word separators
		$words = preg_split('/[\s,]+/', $longtext, null, PREG_SPLIT_NO_EMPTY);
		$add_line = false;
		$current_line = '';
		$lines = array();

		do {
			$word = next($words);
			$wlen = strlen($word);
			if ($wlen > $max)
				continue;
			$current_line = ltrim($current_line);
			$llen = strlen($current_line);
			if (!$wlen && $llen) {
				$lines[] = ltrim($current_line);
				break;
			}
			$add_line = ( $llen + $wlen + 1 > $max);
			if ($add_line && $llen) {
				$lines[] = $current_line;
				$current_line = $word;
			} else {
				$current_line .= ' ' . $word;
			}
		} while ($word);

		return $lines;
	}
}
