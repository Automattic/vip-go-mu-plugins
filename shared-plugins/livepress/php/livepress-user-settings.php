<?php
/**
 * Uses the settings/author API to add settings for individual authors.
 * Generally found under Users->Your Profile
 */

class LivePress_User_Settings {

	/**
	 * @var array $messages Array of updated and error messages.
	 */
	public $messages = array(
		'updated' => array(),
		'error' => array(),
	);

	/**
	 * Constructor.
	 */
	function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'show_user_profile', array( $this, 'user_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'user_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_fields' ) );
		add_action( 'user_profile_update_errors', array( $this, 'save_fields_err' ), 0, 3 );
	}

	/**
	 * Add an error.
	 *
	 * @access private
	 *
	 * @param string $msg Error message.
	 */
	private function add_error( $msg ) {
		$this->messages['error'][] = $msg;
	}

	/**
	 * Save field errors.
	 *
	 * @param array $errors Array of errors, passed by reference.
	 * @param string $update
	 * @param $user
	 * @return mixed
	 */
	function save_fields_err( &$errors, $update, &$user ) {
		foreach($this->messages['error'] as $err) {
			$errors->add('lp_twitter', $err);
			$errors->add('lp_phone_number', $err);
		}
		return $errors;
	}

	/**
	 * Enqueue some styles on the profile page to display our LP form a little nicer.
	 *
	 * @param $hook string Hook suffix of current screen.
	 *
	 * @author tddewey
	 * @return string $hook, unaltered regardless.
	 */
	function admin_enqueue_scripts( $hook ) {
		if ( $hook != 'profile.php' )
			return $hook;

		wp_enqueue_style( 'livepress_admin', LP_PLUGIN_URL . 'css/wp-admin.css' );
		return $hook;
	}

	/**
	 * Extend a user's profile with some user-specific options.
	 *
	 * @author tddewey
	 *
	 * @param $user object WP User object for the user being edited
	 */
	function user_fields( $user ) {

		// Hide form based on permission to edit this profile or edited user to publish posts
		if ( ! current_user_can( 'edit_user', $user->ID ) && user_can( $user, 'publish_posts' ) )
			return;

		$lp_twitter = get_user_meta( $user->ID, 'lp_twitter', true ); // returns empty string if not set, perfect
		$lp_phone_number = get_user_meta( $user->ID, 'lp_phone_number', true ); // returns empty string if not set, perfect

		$lp_options = get_option('livepress');

		// Only display this form if atremote twitter or remote SMS is allowed by an admin.
		if ( ( isset( $lp_options['allow_remote_twitter'] ) && $lp_options['allow_remote_twitter'] == 'allow' ) ||  ( isset( $lp_options['allow_sms'] ) && $lp_options['allow_sms'] == 'allow' ) ):
		?>
		<h3>LivePress</h3>
		<table class="form-table">
		<?php
			if ( ( isset( $lp_options['allow_remote_twitter'] ) && $lp_options['allow_remote_twitter'] == 'allow' ) ):
		?>
		<?php
		// Use actual password for VIP
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ) {

		?>
			<tr>
				<th><label for="lp_user_password"><?php esc_html_e( 'WordPress password', 'livepress' ) ?></label></th>
				<td>
				<?php
					$blogging_tools = new LivePress_Blogging_Tools();
					if ( $blogging_tools->get_have_user_pass( $user->ID ) ) {
				?>
					<div class="password_already_saved">Password already saved. <a class="reset_lp_user_password"><?php esc_html_e( 'Reset password', 'livepress' ) ?></a></div>
				<div class="hidden_lp_user_password hidden">

				<?php
					} else {
				?>
				<div>
				<?php } ?>
					<input type="password" name="lp_user_password" id="lp_user_password" value="" class="regular-text" />
					<table class="description">
						<tr>
							<td><?php esc_html_e( 'Please enter your WordPress password to enable remote publishing', 'livepress' ) ?></td>
						</tr>
					</table>
				</td>
			</tr>
		<?php } ?>

			<tr>
				<th><label for="lp_twitter"><?php esc_html_e( 'Publish from Twitter', 'livepress' ) ?></label></th>
				<td class="input-prepend">
					<span class="add-on">@</span><input type="text" name="lp_twitter" id="lp_twitter" value="<?php echo esc_attr( $lp_twitter ); ?>" class="regular-text" />
					<table class="description">
						<tr>
							<td><code>#lpon</code></td>
							<td><?php esc_html_e( 'Tweet this with a blog title to start publishing from Twitter', 'livepress' ) ?></td>
						</tr>
						<tr>
							<td></td>
							<td><?php esc_html_e( 'All tweets in between are live published to one post', 'livepress' ) ?></td>
						</tr>
						<tr>
							<td><code>#lpoff</code></td>
							<td><?php esc_html_e( 'Tweet this on your last update to stop', 'livepress' ) ?></td>
						</tr>
					</table>
				</td>
			</tr>
		<?php
			endif;
			if ( ( isset( $lp_options['allow_sms'] ) && $lp_options['allow_sms'] == 'allow' ) ):
		?>
		<tr>
			<th><label for="lp_phone_number"><?php esc_html_e( 'Your Mobile Phone Number, to publish from SMS', 'livepress' ) ?></label></th>
			<td class="input-prepend">
				<input type="text" name="lp_phone_number" id="lp_phone_number" value="<?php echo esc_attr( $lp_phone_number ); ?>" class="regular-text" />
				<table class="description">
					<tr>
						<td colspan="2"><?php esc_html_e( 'Text commands from your phone to LivePress at: ', 'livepress' ) ?><a href="sms:14157020916">1-415-702-0916</a></td>
					</tr>
					<tr>
						<td><code>#new post title</code></td>
						<td><?php esc_html_e( 'Create a new live blog post with title "post title"', 'livepress' ) ?></td>
					</tr>
					<tr>
						<td><code>#list</code></td>
						<td><?php esc_html_e( 'List recent live blog posts', 'livepress' ) ?></td>
					</tr>
					<tr>
						<td colspan="2"><?php echo wp_kses_post( __( '...and much more, for a complete list text <code>#help</code> to', 'livepress' ) ); ?> <a href="sms:14157020916">1-415-702-0916</a></td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
			endif;
		?>
		</table>
		<?php
		endif;
	}

	/**
	 * Save the user settings.
	 *
	 * @author tddewey
	 *
	 * @param $user_id integer user ID being updated.
	 *
	 * @return integer returns the $user_id regardless of success or failure.
	 */
	function save_fields( $user_id ) {

		// Check permissions and nonce.
		if ( ( ! current_user_can( 'edit_user', $user_id ) && wp_verify_nonce( $_POST['_wpnonce'] ) ) ) {
			return $user_id;
		}

		$old_lp_twitter   = get_user_meta( $user_id, 'lp_twitter', true ); // returns empty string if not set, perfect
		$lp_twitter       = isset( $_POST['lp_twitter'] ) ? sanitize_text_field( $_POST['lp_twitter'] ) : '';
		$lp_user_password = isset( $_POST['lp_user_password'] ) ? sanitize_text_field( $_POST['lp_user_password'] ) : '';

		if( $old_lp_twitter != $lp_twitter || '' !== $lp_user_password ) {
			$user = get_userdata( $user_id );

			$options = get_option(LivePress_Administration::$options_name);
			$livepress_com = new LivePress_Communication($options['api_key']);

			$error_message = '';
			try {
				$return_code = $livepress_com->manage_remote_post_from_twitter( $lp_twitter, $user->user_login );
			} catch ( LivePress_Communication_Exception $e ) {
				$return_code   = $e->get_code();
				$error_message = $e->getMessage();
			}
			/* User not found at livepress, or password invalid, or WordPress password entered */
			if ( $return_code == 403 || '' !== $lp_user_password ) {
				$la = new LivePress_Administration();
				$error_message = $livepress_com->get_last_error_message();
				if ( ! $la->enable_remote_post( $user_id, $lp_user_password ) ) {
					$this->add_error( "Error from the LivePress service: ", $error_message );
				} else {
					$error_message = '';
					try {
						$return_code = $livepress_com->manage_remote_post_from_twitter( $lp_twitter, $user->user_login );
					} catch ( LivePress_Communication_Exception $e ) {
						$return_code   = $e->get_code();
						$error_message = $e->getMessage();
					}
				}
			}

			if ( $return_code != 200 && $return_code != "OK." ) {
				if ( strlen( $error_message ) > 0 ) {
					$this->add_error( "Problem with setting twitter user: " . $error_message ); // Show existing error
				} else {
					$this->add_error( "Problem with setting twitter user: error #" . $return_code ); // Show error code if no msg
				}
			} else { // No error
				if ( empty( $lp_twitter ) ) { // None left, delete meta
					delete_user_meta( $user_id, 'lp_twitter' );
				} else { // Update meta with current lp_twitter
					update_user_meta( $user_id, 'lp_twitter', $lp_twitter );
				}
			}
		}

		$old_lp_phone_number = get_user_meta( $user_id, 'lp_phone_number', true ); // returns empty string if not set, perfect
		$lp_phone_number     = isset( $_POST['lp_phone_number'] ) ? sanitize_text_field( $_POST['lp_phone_number'] ) : '';
		if($old_lp_phone_number != $lp_phone_number) {
			$user = get_userdata( $user_id );

			$options = get_option(LivePress_Administration::$options_name);
			$livepress_com = new LivePress_Communication($options['api_key']);

			$error_message = '';
			try {
				$return_code = $livepress_com->set_phone_number( $lp_phone_number, $user->user_login );
			} catch ( LivePress_Communication_Exception $e ) {
				$return_code   = $e->get_code();
				$error_message = $e->getMessage();
			}

			if ( $return_code == 403 ) { /* User not found at livepress, or password invalid */
				$la = new LivePress_Administration();
				$error_message = $livepress_com->get_last_error_message();
				if ( ! $la->enable_remote_post( $user_id, $lp_user_password ) ) {
					$this->add_error( "Error from the LivePress service: ", $error_message );
				} else {
					$error_message = '';
					try {
						$return_code = $livepress_com->set_phone_number( $lp_phone_number, $user->user_login );
					} catch ( LivePress_Communication_Exception $e ) {
						$return_code   = $e->get_code();
						$error_message = $e->getMessage();
					}
				}
			}

			if ( $return_code != 200 && $return_code != "OK." ) {
				if ( strlen( $error_message ) > 0 ) {
					$this->add_error( "Problem with setting phone number: " . $error_message ); // Show existing error
				} else {
					$this->add_error( "Problem with setting phone number: error #" . $return_code ); // Show error code if no msg
				}
			} else { // No error
				if ( empty( $lp_phone_number ) ) { // None left, delete meta
					delete_user_meta( $user_id, 'lp_phone_number' );
				} else { // Update meta with current lp_phone_number
					update_user_meta( $user_id, 'lp_phone_number', $lp_phone_number );
				}
			}
		}

		return $user_id;
	}

}

$livepress_user_settings = new LivePress_User_Settings();
