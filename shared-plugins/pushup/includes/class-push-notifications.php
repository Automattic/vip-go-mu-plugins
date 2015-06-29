<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Class PushUp_Notifications
 *
 * This class facilitates pushing OSX notifications by hooking into `transition_post_status` and checking for when a
 * post type moves from not published to published. It provides a way for us to communicate with our "home base" and
 * easily send push notifications using our credentials.
 */
class PushUp_Notifications {

	/**
	 * The URL our API will use for communicating with home. Valid credentials must be in place for this to work.
	 *
	 * @var string
	 */
	protected static $_api_url = 'https://push.10up.com';

	/**
	 * This property is used to store the device key we plan on sending a push notification when the recipient mode is
	 * set to `single`. Note this property is not used when the recipient mode is set to `all`.
	 *
	 * @var string
	 */
	protected static $_device_token = '';

	/**
	 * The meta key that will be used to store the push setting for post types. This meta key indicates whether or not
	 * a push notification should be sent when the post this meta key is attached to finally becomes published.
	 *
	 * @var string
	 */
	protected static $_meta_key = '_pushup-notifications-push-setting';

	/**
	 * Responsible for storing whether or not we're blasting a notification to `all` recipients who have registered with
	 * a particular api key and username or to a `single` recipient with a specified device token.
	 *
	 * @var string
	 */
	protected static $_recipient_mode = 'all';

	/**
	 * Singleton method to retrieve the once-instantiated instance of this class.
	 *
	 * @return null|PushUp_Notifications
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
			self::_add_actions();
		}

		return $instance;
	}

	/**
	 * An empty constructor
	 */
	public function __construct() { /* Purposely do nothing here */ }

	/**
	 * Handles adding all of the actions necessary for this class to be used correctly.
	 */
	protected static function _add_actions() {
		add_action( 'transition_post_status',      array( __CLASS__, 'transition_post_status'      ), 10, 3 );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'post_submitbox_misc_actions' )        );
		add_action( 'wp_enqueue_scripts',          array( __CLASS__, 'wp_enqueue_scripts'          )        );
		add_filter( 'map_meta_cap',                array( __CLASS__, 'map_meta_cap'                ), 10, 4 );
	}

	/**
	 * Responsible for queueing any scripts and even localizing any data needed for those scripts to run. This function
	 * should only be called through the WordPress action hook `wp_enqueue_scripts`
	 */
	public static function wp_enqueue_scripts() {

		// Get data required for theme-side API requests
		$authentication = PushUp_Notifications_Authentication::get_authentication_data();
		if ( false === $authentication ) {
			return;
		}

		// Get notification prompt configuration (0 = custom)
		$prompt_settings = PushUp_Notifications_Core::get_prompt_setting();
		$prompts = ( $prompt_settings['prompt'] == 'custom' ) ? 0 : (int) $prompt_settings['prompt_views'];

		// Allow base path to be filtered
		$base = apply_filters( 'pushup-notification-base-script-path', plugins_url( '', dirname( __FILE__ ) ) );

		// Enqueue the main PushUp JS used to prompt visitors
		$append = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_script( 'pushup', $base . '/js/pushup' . $append . '.js', array( 'jquery' ), PushUp_Notifications_Core::get_script_version(), true );

		// Localize the Notification prompt strings
		wp_localize_script( 'pushup', 'PushUpNotificationSettings', array(
			'domain'        => $authentication['domain'],
			'userID'        => $authentication['user_id'],
			'websitePushID' => $authentication['website_push_id'],
			'webServiceURL' => self::$_api_url,
			'prompt'        => (int) $prompts,
		) );
	}

	/**
	 * Helper function responsible for setting the "push setting" of a post. See self::$_meta_key for more information.
	 *
	 * @param array $value
	 * @param int $post_id
	 * @return bool
	 */
	public static function set_push_setting( $value = array(), $post_id = 0 ) {

		if ( empty( $post_id ) ) {
			return false;
		}

		if ( empty( $value ) ) {
			return delete_post_meta( $post_id, self::$_meta_key );
		} else {
			return update_post_meta( $post_id, self::$_meta_key, $value );
		}
	}

	/**
	 * Gets the previously set "push setting" for a given post with a $post_id of the value passed to this function.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function get_push_setting( $post_id = 0 ) {

		// Default push setting values
		$retval = array(
			'time'   => 0,
			'status' => 'unpushed'
		);

		// Post ID was passed
		if ( ! empty( $post_id ) ) {

			// Attempt to get post meta
			$setting = get_post_meta( $post_id, self::$_meta_key, true );

			// Old style meta value
			if ( is_numeric( $setting ) ) {
				$retval['time'] = intval( $setting );

				// Update the status array if time value exists
				if ( !empty( $retval['time'] ) ) {
					$retval['status'] = 'pushed';
				}

			// Backwards compatibility with original beta
			} elseif ( 'true' === $setting ) {
				$retval = array(
					'time'   => get_post_time( 'U', true, $post_id ),
					'status' => 'pushed'
				);

			// New style meta value
			} elseif ( is_array( $setting ) ) {
				$retval = $setting;
			}
		}

		// Return the retval
		return $retval;
	}

	/**
	 * Map plugin capabilities to existing WordPress role capabilities
	 *
	 * @param array $caps
	 * @param string $cap
	 * @param int $user_id
	 * @param array $args
	 * @return array
	 */
	public static function map_meta_cap( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

		// What cap are we checking?
		switch ( $cap ) {

		// Custom cap for the publish box
		case 'pushup_push_posts' :

			// Get the post from the passed $args, bail if it doesn't exist
			$post = get_post( $args[0] );
			if ( empty( $post ) ) {
				return $caps;
			}

			// Get the post type and assign necessary caps
			$post_type = get_post_type_object( $post->post_type );
			$caps      = apply_filters( 'pushup_push_posts_required_caps', array( $post_type->cap->edit_others_posts ) );
			break;
		}

		return $caps;
	}

	/**
	 * Renders the checkbox and custom JavaScript needed to ensure that the checkbox performs the way we need it to.
	 *
	 * Note: there was an interesting problem for when the checkbox transitioned through the save processes and the
	 * value of the 'push setting' would be wiped because the checkbox wasn't checked or simply not even posted but WAS
	 * checked... This is the reason for the JavaScript below.
	 */
	public static function post_submitbox_misc_actions() {

		// Get some post data
		$post_id     = get_the_ID();
		$post_status = get_post_status( $post_id );
		$post_type   = get_post_type( $post_id );

		// Bail if current user cannot edit others posts
		if ( ! current_user_can( 'pushup_push_posts', $post_id ) ) {
			return;
		}

		// Bail if not an allowed post type
		if ( ! self::_is_post_type_allowed( $post_type ) ) {
			return;
		}

		// Get the push setting
		$value = self::get_push_setting( $post_id ); ?>

		<style type="text/css">
			.pushup-notification-pushed-icon {
				margin: 1px 8px 2px 1px;
				float: left;
			}
			.pushup-notification-pushed-time:before {
				speak: none;
				display: inline-block;
				padding: 0 2px 0 0;
				position: relative;
				vertical-align: top;
				-webkit-font-smoothing: antialiased;
				-moz-osx-font-smoothing: grayscale;
				text-decoration: none !important
				background: url('../images/pushup-grey.svg') no-repeat;
			}
		</style>

		<div class="misc-pub-section" id="pushup-notifications-container">

			<?php if ( !empty( $value['time'] ) && ( $value['status'] === 'pushed' ) ) : ?>

				<img class="pushup-notification-pushed-icon" src="<?php echo plugin_dir_url( __FILE__ ); ?>../images/pushup-grey.svg" width="16" height="16" />
				<span class="pushup-notification-pushed-time"><?php printf( __( 'Pushed on: %s', 'pushup' ), '<strong>' . date_i18n( 'M j, Y @ G:i', $value['time'], false ) . '</strong>' ); ?></span>

			<?php elseif ( PushUp_Notifications_Authentication::is_authenticated() && PushUp_Notifications_JSON_API::is_domain_enabled() ) : ?>

				<?php wp_nonce_field( 'push_notification_nonce', 'post_nonce_' . $post_id ); ?>
				<input <?php checked( !empty( $value['time'] ) ); ?> title="<?php esc_html_e( 'Push to registered OS X subscribers upon publication', 'pushup' ); ?>" type="checkbox" name="pushup-notification-creation" id="pushup-notification-creation" />

				<?php if ( 'error' === $value['status'] ) : ?>
					<label title="<?php esc_html_e( 'Push to registered OS X subscribers upon publication', 'pushup' ); ?>" for="pushup-notification-creation"><?php esc_html_e( 'Push Error. Try again!', 'pushup' ); ?></label>
				<?php else : ?>
					<label title="<?php esc_html_e( 'Push to registered OS X subscribers upon publication', 'pushup' ); ?>" for="pushup-notification-creation"><?php esc_html_e( 'Push desktop notification', 'pushup' ); ?></label>
				<?php endif; ?>

				<?php if ( $post_status === 'published' || $value['time'] === 0 ) : ?>
					<input type="hidden" name="pushup-notification-creation" value="<?php echo !empty( $value['time'] ) ? 'on' : 'off'; ?>" id="pushup-notification-hidden-input" />
				<?php endif; ?>

			<?php endif; ?>

		</div>

		<script type="text/javascript">
			( function( window, $, undefined ) {
				var $container = $( document.getElementById( 'pushup-notifications-container' ) );
				var $checkbox = $( document.getElementById( 'pushup-notification-creation' ) );

				function checkBoxChanged( event ) {
					var $checkboxes = $container.find( 'input[type=checkbox]:checked' );

					// check to see if any of these checkboxes match the one we're looking for already
					var isChecked = false;
					$checkboxes.each( function( index, element ) {
						if ( element.getAttribute( 'id' ) === 'pushup-notification-creation' ) {
							isChecked = true;
						}
					} );

					if ( isChecked === true ) {
						removeHiddenInput();
					} else {
						addHiddenInput();
					}
				}

				function removeHiddenInput() {
					$( document.getElementById( 'pushup-notification-hidden-input' ) ).remove();
				}

				function addHiddenInput() {
					var input = document.createElement( 'input' );
					input.type = 'hidden';
					input.name = 'pushup-notification-creation';
					input.value = 'off';
					input.id = 'pushup-notification-hidden-input';

					$container.append( input );
				}

				$checkbox.on( 'change', checkBoxChanged );

			} )( window, jQuery );
		</script>

		<?php
	}

	/**
	 * This function is responsible for checking whether or not a post is:
	 * 		1. transitioning from not published to published &
	 * 		2. has the "push setting" set to true
	 *
	 * This function should only be called automatically through the WordPress action hook `transition_post_status`.
	 * If the criteria above (1 & 2) are met, it handles gathering the information we need to send a push notification
	 * and then sends it.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param null|WP_Post $post
	 */
	public static function transition_post_status( $new_status = '', $old_status = '', $post = null ) {

		/** Permissions *******************************************************/

		// Bail if post is empty
		if ( empty( $post ) ) {
			return;
		}

		// Bail if not an allowed post type
		if ( ! self::_is_post_type_allowed( get_post_type( $post ) ) ) {
			return;
		}

		// Only users that can edit this post can push
		if ( ! current_user_can( 'edit_post', $post->ID ) && ! defined( 'DOING_CRON' ) ) {
			return;
		}

		// Bail for new auto-draft transitions
		if ( 'auto-draft' === $new_status && 'new' === $old_status ) {
			return;
		}

		// No autosaves or revisions
		if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
			return;
		}

		/** Already Pushed ****************************************************/

		// Get push time if it's already set
		$push_setting = self::get_push_setting( $post->ID );

		// Bail if the push status is publish, since posts cannot be unpushed
		if ( ! empty( $push_setting['time'] ) && ( 'pushed' === $push_setting['status'] ) ) {
			return;
		}

		/** Validate Push Status **********************************************/

		/**
		 * Force pushed status to unpushed if no time exists.
		 *
		 * This fixes otherwise unexpected data corruption from who-knows-where, and prevents a post thinking it's been
		 * pushed, without knowing when it happened, resulting in an inconsistent checkbox UI.
		 **/
		if ( empty( $push_setting['time'] ) ) {
			$push_setting['status'] = 'unpushed';
		}

		/** Validate Push Time ************************************************/

		// Current author is saving or updating a post
		if ( self::_is_post_request() ) {

			// Checkbox is unchecked so force time to 0
			if ( ! defined( 'DOING_CRON' ) && ( empty( $_POST[ 'pushup-notification-creation' ] ) || ( 'off' === $_POST[ 'pushup-notification-creation' ] ) ) ) {
				$push_setting['time'] = 0;

			// Checkbox is checked, so force time to current time
			} elseif ( 'on' === $_POST[ 'pushup-notification-creation' ] ) {
				$push_setting['time'] = current_time( 'timestamp' );
			}

		// Future dated post switching to publish
		} elseif ( ( 'publish' === $new_status ) && ( 'future' === $old_status ) ) {

			// Checkbox was not checked when post was saved, so force time to 0
			if ( empty( $push_setting['time'] ) ) {
				$push_setting['time'] = 0;

			// Checkbox was previously checked, so update the push time
			} else {
				$push_setting['time'] = current_time( 'timestamp' );
			}
		}

		/** Check the API *****************************************************/

		// Don't push if post was previously pushed
		if ( ( 'publish' === $new_status ) && ( !empty( $push_setting['time'] ) ) ) {
			$post_url      = PushUp_Notifications_Core::get_shortlink( $post->ID );
			$url_parameter = str_replace( array( 'http://', 'https://' ), '', $post_url );

			// cap for title is: 35 characters (before being trimmed by OSX)
			// cap for body is: 133 characters (before being trimmed by OSX)
			$title         = PushUp_Notifications_Core::get_post_title();
			$body          = self::_maybe_trim_post_title( apply_filters( 'the_title', $post->post_title, $post->ID ) );
			$action        = 'See Post';
			$pushed        = self::send_message( $title, $body, $action, array( $url_parameter ) );
		} else {
			$pushed        = null;
		}

		/** Push Status *******************************************************/

		// Setup the push status
		if ( is_array( $pushed ) ) {
			$push_setting = $pushed;
		} elseif ( 'pushed' !== $push_setting['status'] ) {
			$push_setting['status'] = 'unpushed';
		}

		// Update the push setting
		self::set_push_setting( $push_setting,  $post->ID );
	}

	/**
	 * Return true|false if this is a POST request
	 *
	 * @return bool
	 */
	protected static function _is_post_request() {
		return (bool) ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ) );
	}

	/**
	 * Is a post type allowed to be pushed?
	 *
	 * @param string $post_type
	 * @return bool
	 */
	protected static function _is_post_type_allowed( $post_type = '' ) {

		// Allowed post types
		$allowed_types = apply_filters( 'pushup_allowed_post_types', array( 'post' ) );

		// Return true|false if post type is in allowed types array
		return (bool) in_array( $post_type, $allowed_types );
	}

	/**
	 * This function conditionally trims the title string passed to it when the string is over 130 characters... We
	 * needed this function because the limit for the body of the push notification has a cap of 133 characters before
	 * it automatically gets trimmed by OSX.
	 *
	 * @param string $title
	 * @return string
	 */
	protected static function _maybe_trim_post_title( $title = '' ) {

		if ( strlen( $title ) > 130 ) {
			$title = substr( $title, 0, 130 ) . '...';
		}

		return $title;
	}

	/**
	 * Helper function that sets the device token that the push notification will be sent to when the recipient mode is
	 * set to `single`
	 *
	 * @param string $device_token
	 */
	public static function set_device_token( $device_token = '' ) {
		self::$_device_token = $device_token;
	}

	/**
	 * Sets the recipient mode of the API call to either `single` or `all` based on the audience we want to send the
	 * push notification to.
	 *
	 * Note: if the recipient mode is set to `single`, we require a device token to be set with `set_device_token`.
	 *
	 * @param string $mode
	 * @return bool
	 */
	public static function set_recipient_mode( $mode = 'all' ) {

		if ( $mode !== 'all' && $mode !== 'single' ) {
			return false;
		}

		self::$_recipient_mode = $mode;
		return true;
	}

	/**
	 * Handles connecting to our API via `wp_remote_post()`. This function is responsible for pushing the notification
	 * details to our push notification server which will then process everything and validate the API request before
	 * sending it.
	 *
	 * @param string $title
	 * @param string $body
	 * @param string $action
	 * @param array $url_arguments
	 * @return bool
	 */
	public static function send_message( $title = '', $body = '', $action = '', $url_arguments = array() ) {
		$username = PushUp_Notifications_Core::get_username();
		$api_key  = PushUp_Notifications_Core::get_api_key();

		if ( empty( $username ) || empty( $api_key ) ) {
			return false;
		}

		$params = array(
			'method'    => 'POST',
			'timeout'   => 12,
			'sslverify' => false,
			'body'      => array(
				'title'         => $title,
				'body'          => $body,
				'action'        => $action,
				'username'      => $username,
				'api_key'       => $api_key,
				'url_arguments' => $url_arguments,
				'mode'          => self::$_recipient_mode,
				'domain'        => PushUp_Notifications_Core::get_site_url(),
			)
		);

		if ( self::$_recipient_mode === 'single' ) {
			if ( is_null( self::$_device_token ) ) {
				return false;
			}
			$params[ 'body' ][ 'device_token' ] = self::$_device_token;
		}

		/**
		 * Filter the push notifications API URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $_api_url The push notifications API URL.
		 */
		$api_url = apply_filters( 'pushup_notifications_api_url', self::$_api_url ) . '/send';

		// Default return value
		$retval = array(
			'time'   => current_time( 'timestamp' ),
			'status' => 'error'
		);

		// Attempt to make the remote request
		$result = wp_remote_post( $api_url, $params );
		if ( is_wp_error( $result ) ) {
			return $retval;
		}

		// Parse the body of the json request
		$body = wp_remote_retrieve_body( $result );
		$data = @json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return $retval;
		}

		// Set the status to 'pushed'
		if ( isset( $data[ 'status' ] ) && $data[ 'status' ] === 'ok' ) {
			$retval['status'] = 'pushed';
		}

		// Return the return value
		return $retval;
	}
}
