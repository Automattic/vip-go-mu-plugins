<?php
/**
 * Support User management
 */

namespace Automattic\VIP\Support_User;

use WP_Error;
use WP_User;

/**
 * Manages VIP Support users.
 *
 * @package WPCOM_VIP_Support_User
 **/
class User {

	/**
	 * GET parameter for a message: We blocked this user from the
	 * support role because they're not an A12n.
	 */
	const MSG_BLOCK_UPGRADE_NON_A11N = 'vip_support_msg_1';

	/**
	 * GET parameter for a message: We blocked this user from the
	 * support role because they have not verified their
	 * email address.
	 */
	const MSG_BLOCK_UPGRADE_VERIFY_EMAIL = 'vip_support_msg_2';

	/**
	 * GET parameter for a message: We blocked this NEW user from
	 * the support role because they're not an A12n.
	 */
	const MSG_BLOCK_NEW_NON_VIP_USER = 'vip_support_msg_3';

	/**
	 * GET parameter for a message: We blocked this user from
	 * LEAVING the support role because they have not verified
	 * their email address.
	 */
	const MSG_BLOCK_DOWNGRADE = 'vip_support_msg_4';

	/**
	 * GET parameter for a message: This user was added to the
	 * VIP Support role.
	 */
	const MSG_MADE_VIP = 'vip_support_msg_5';

	/**
	 * GET parameter for a message: We downgraded this user from
	 * the support role because their email address is no longer
	 * verified.
	 */
	const MSG_DOWNGRADE_VIP_USER = 'vip_support_msg_6';

	/**
	 * Meta key for the email verification data.
	 */
	const META_VERIFICATION_DATA = '_vip_email_verification_data';

	/**
	 * Meta key flag that this user needs verification
	 */
	const META_EMAIL_NEEDS_VERIFICATION = '_vip_email_needs_verification';

	/**
	 * Meta key for the email which HAS been verified.
	 */
	const META_EMAIL_VERIFIED = '_vip_verified_email';

	/**
	 * GET parameter for the code in the verification link.
	 */
	const GET_EMAIL_VERIFY = 'vip_verify_code';

	/**
	 * GET parameter for the user ID for the user being verified.
	 */
	const GET_EMAIL_USER_LOGIN = 'vip_user_login';

	/**
	 * GET parameter to indicate to trigger a resend if true.
	 */
	const GET_TRIGGER_RESEND_VERIFICATION = 'vip_trigger_resend';

	/**
	 * Cron action to purge support user.
	 */
	const CRON_ACTION = 'wpcom_vip_support_remove_user_via_cron';

	/**
	 * Meta key to identify support users
	 */
	const VIP_SUPPORT_USER_META_KEY = '_vip_support_user';

	/**
	 * Base email address for VIP Support user aliases.
	 */
	const VIP_SUPPORT_EMAIL_ADDRESS = 'vip-support@automattic.com';

	/**
	 * The Gravatar URL for `VIP_SUPPORT_EMAIL_ADDRESS`.
	 */
	const VIP_SUPPORT_EMAIL_ADDRESS_GRAVATAR = 'https://secure.gravatar.com/avatar/c84e75679a6342a8a28793784098c0ee57669854bc0d006b6a7a49a275177696';

	/**
	 * A flag to indicate reversion and then to prevent recursion.
	 *
	 * @var bool True if the role is being reverted
	 */
	protected $reverting_role;

	/**
	 * Set to a string to indicate a message to replace, but
	 * defaults to false.
	 *
	 * @var bool|string
	 */
	protected $message_replace;

	/**
	 * A flag to indicate the user being registered is an
	 * A12n (i.e. VIP).
	 *
	 * @var bool
	 */
	protected $registering_a11n;

	/**
	 * Initiate an instance of this class if one doesn't
	 * exist already. Return the User instance.
	 *
	 * @access @static
	 *
	 * @return User object The instance of User
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new User();
		}

		return $instance;
	}

	/**
	 * Class constructor. Handles hooking actions and filters,
	 * and sets some properties.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );
		add_action( 'set_user_role', array( $this, 'action_set_user_role' ), 10, 3 );
		add_action( 'user_register', array( $this, 'action_user_register' ) );
		add_action( 'parse_request', array( $this, 'action_parse_request' ) );
		add_action( 'personal_options', array( $this, 'action_personal_options' ) );
		add_action( 'load-user-edit.php', array( $this, 'action_load_user_edit' ) );
		add_action( 'load-profile.php', array( $this, 'action_load_profile' ) );
		add_action( 'profile_update', array( $this, 'action_profile_update' ) );
		add_action( 'admin_head', array( $this, 'action_admin_head' ) );
		add_action( 'wp_login', array( $this, 'action_wp_login' ), 10, 2 );

		// May be added by Cron Control, if used together.
		// Ensure cleanup runs regardless.
		if ( ! has_action( self::CRON_ACTION ) ) {
			add_action( self::CRON_ACTION, array( __CLASS__, 'do_cron_cleanup' ) );

			if ( ! wp_next_scheduled( self::CRON_ACTION ) ) {
				wp_schedule_event( time(), 'hourly', self::CRON_ACTION );
			}
		}

		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ) );
		add_filter( 'removable_query_args', array( $this, 'filter_removable_query_args' ) );
		add_filter( 'user_email', array( $this, 'filter_vip_support_email_aliases' ), 10, 2 );
		add_filter( 'get_avatar_url', array( $this, 'filter_vip_support_email_gravatars' ), 10, 3 );
		add_filter( 'login_redirect', array( $this, 'disable_admin_email_check' ), 10, 3 );

		$this->reverting_role   = false;
		$this->message_replace  = false;
		$this->registering_a11n = false;
	}

	// HOOKS
	// =====

	/**
	 * Hooks the admin_head action to add some CSS into the
	 * user edit and profile screens.
	 */
	public function action_admin_head() {
		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return;
		}

		if ( in_array( $current_screen->base, array( 'user-edit', 'profile' ) ) ) {
			?>
			<style type="text/css">
				.vip-support-email-status {
					padding-left: 1em;
				}
				.vip-support-email-status .dashicons {
					line-height: 1.6;
				}
				.email-not-verified {
					color: #dd3d36;
				}
				.email-verified {
					color: #7ad03a;
				}
			</style>
			<?php
		}
	}

	/**
	 * Hooks the load action on the user edit screen to
	 * send verification email if required.
	 */
	public function action_load_user_edit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET[ self::GET_TRIGGER_RESEND_VERIFICATION ] ) && isset( $_GET['user_id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = absint( $_GET['user_id'] );
			$this->send_verification_email( $user_id );
		}
	}

	/**
	 * Hooks the load action on the profile screen to
	 * send verification email if required.
	 */
	public function action_load_profile() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET[ self::GET_TRIGGER_RESEND_VERIFICATION ] ) ) {
			$user_id = get_current_user_id();
			$this->send_verification_email( $user_id );
		}
	}

	/**
	 * Hooks the personal_options action on the user edit
	 * and profile screens to add verification status for
	 * the user's email.
	 *
	 * @param object $user The WP_User object representing the user being edited
	 */
	public function action_personal_options( $user ) {
		if ( ! self::is_a8c_email( $user->user_email ) ) {
			return;
		}

		if ( $this->user_has_verified_email( $user->ID ) ) {
			?>
			<em id="vip-support-email-status" class="vip-support-email-status email-verified"><span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'email is verified', 'vip-support' ); ?>
			</em>
			<?php
		} else {
			?>
			<em id="vip-support-email-status" class="vip-support-email-status email-not-verified"><span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'email is not verified', 'vip-support' ); ?>
			</em>
			<?php
		}
		?>
		<script type="text/javascript">
			jQuery( 'document').ready( function( $ ) {
				$( '#email' ).after( $( '#vip-support-email-status' ) );
			} );
		</script>
		<?php
	}

	/**
	 * Hooks the admin_notices action to add some admin notices,
	 * also resends verification emails when required.
	 */
	public function action_admin_notices() {
		$error_html   = false;
		$message_html = false;
		$screen       = get_current_screen();

		// Messages on the users list screen
		if ( in_array( $screen->base, array( 'users', 'user-edit', 'profile' ) ) ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$update = sanitize_text_field( $_GET['update'] ?? false );

			switch ( $update ) {
				case self::MSG_BLOCK_UPGRADE_NON_A11N:
					$error_html = __( 'Only users with a recognised Automattic email address can be assigned the VIP Support role.', 'vip-support' );
					break;
				case self::MSG_BLOCK_UPGRADE_VERIFY_EMAIL:
				case self::MSG_MADE_VIP:
					$error_html = __( 'This user’s Automattic email address must be verified before they can be assigned the VIP Support role.', 'vip-support' );
					break;
				case self::MSG_BLOCK_NEW_NON_VIP_USER:
					$error_html = __( 'Only Automattic staff can be assigned the VIP Support role, the new user has been made a "subscriber".', 'vip-support' );
					break;
				case self::MSG_BLOCK_DOWNGRADE:
					$error_html = __( 'VIP Support users can only be assigned the VIP Support role, or deleted.', 'vip-support' );
					break;
				case self::MSG_DOWNGRADE_VIP_USER:
					$error_html = __( 'This user’s email address has changed, and as a result they are no longer in the VIP Support role. Once the user has verified their new email address they will have the VIP Support role restored.', 'vip-support' );
					break;
				default:
					break;
			}
		}

		// Messages on the user's own profile edit screen
		if ( 'profile' == $screen->base ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET[ self::GET_TRIGGER_RESEND_VERIFICATION ] ) ) {
				$message_html = __( 'The verification email has been sent, please check your inbox. Delivery may take a few minutes.', 'vip-support' );
			} else {
				$user_id     = get_current_user_id();
				$user        = get_user_by( 'id', $user_id );
				$resend_link = $this->get_trigger_resend_verification_url();
				if ( self::is_a8c_email( $user->user_email ) && ! $this->user_has_verified_email( $user->ID ) ) {
					// translators: 1 - link to resemd the email
					$error_html = sprintf( __( 'Your Automattic email address is not verified, <a href="%s">re-send verification email</a>.', 'vip-support' ), esc_url( $resend_link ) );
				}
			}
		}

		// Messages on the user edit screen for another user
		if ( 'user-edit' == $screen->base ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET[ self::GET_TRIGGER_RESEND_VERIFICATION ] ) ) {
				$message_html = __( 'The verification email has been sent, please ask the user to check their inbox. Delivery may take a few minutes.', 'vip-support' );
			} else {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user_id     = absint( $_GET['user_id'] ?? 0 );
				$user        = get_user_by( 'id', $user_id );
				$resend_link = $this->get_trigger_resend_verification_url();
				if ( self::is_a8c_email( $user->user_email ) && ! $this->user_has_verified_email( $user->ID ) && self::user_has_vip_support_role( $user->ID ) ) {
					// translators: 1 - link to resend the email
					$error_html = sprintf( __( 'This user’s Automattic email address is not verified, <a href="%s">re-send verification email</a>.', 'vip-support' ), esc_url( $resend_link ) );
				}
			}
		}

		// For is-dismissible see https://make.wordpress.org/core/2015/04/23/spinners-and-dismissible-admin-notices-in-4-2/
		if ( $error_html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- we construct the message from the trusted data
			echo '<div id="message" class="notice is-dismissible error"><p>' . $error_html . '</p></div>';
		}

		if ( $message_html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- we construct the message from the trusted data
			echo '<div id="message" class="notice is-dismissible updated"><p>' . $message_html . '</p></div>';
		}
	}

	/**
	 * Hooks the set_user_role action to check if we're setting the user to the
	 * VIP Support role. If we are setting to the VIP Support role, various checks
	 * are run, and the transition may be reverted.
	 *
	 * @param int $user_id The ID of the user having their role changed
	 * @param string $role The name of the new role
	 * @param array $old_roles Any roles the user was assigned to previously
	 */
	public function action_set_user_role( $user_id, $role, $old_roles ) {
		// Avoid recursing, while we're reverting
		if ( $this->reverting_role ) {
			return;
		}
		$user = new WP_User( $user_id );

		// Try to make the conditional checks clearer
		$becoming_support         = ( Role::VIP_SUPPORT_ROLE == $role );
		$valid_and_verified_email = ( self::is_a8c_email( $user->user_email ) && $this->user_has_verified_email( $user_id ) );

		if ( $becoming_support && ! $valid_and_verified_email ) {
			$this->reverting_role = true;
			// @FIXME This could be expressed more simply, probably :|
			if ( ! is_array( $old_roles ) || ! isset( $old_roles[0] ) ) {
				if ( self::is_a8c_email( $user->user_email ) ) {
					$revert_role_to = Role::VIP_SUPPORT_INACTIVE_ROLE;
				} else {
					$revert_role_to = 'subscriber';
				}
			} else {
				$revert_role_to = $old_roles[0];
			}
			$this->demote_user_from_vip_support_to( $user->ID, $revert_role_to );
			if ( self::is_a8c_email( $user->user_email ) && ! $this->user_has_verified_email( $user_id ) ) {
				$this->message_replace = self::MSG_BLOCK_UPGRADE_VERIFY_EMAIL;
				$this->send_verification_email( $user_id );
			} else {
				$this->message_replace = self::MSG_BLOCK_UPGRADE_NON_A11N;
			}
			$this->reverting_role = false;
		}
	}

	/**
	 * Filters wp_redirect so we can replace the query string arguments
	 * and manipulate the admin notice shown to the user to reflect what
	 * has happened (e.g. role setting has been rejected as the user is
	 * not an A12n).
	 *
	 * @param $location
	 *
	 * @return string
	 */
	public function filter_wp_redirect( $location ) {
		if ( ! $this->message_replace && ! $this->registering_a11n ) {
			return $location;
		}
		if ( $this->message_replace ) {
			$location = add_query_arg( array( 'update' => rawurlencode( $this->message_replace ) ), $location );
			$location = esc_url_raw( $location );
		}
		if ( $this->registering_a11n ) {
			$location = add_query_arg( array( 'update' => rawurlencode( self::MSG_MADE_VIP ) ), $location );
			$location = esc_url_raw( $location );
		}
		return $location;
	}

	/**
	 * Filters a users email address, removing the username if the email
	 * is a VIP Support email alias.
	 *
	 * This helps simplify support email aliases in the users list so
	 * they're not as lengthy and easier to understand at a glance.
	 *
	 * @param string $email The email address of a user.
	 *
	 * @return string
	 */
	public function filter_vip_support_email_aliases( $email, $id ) {
		if ( is_admin() && self::is_a8c_email( $email ) && $this->has_vip_support_meta( $id ) ) {
			return self::VIP_SUPPORT_EMAIL_ADDRESS;
		}

		return $email;
	}

	/**
	 * Filters a users Gravatar URL, altering it if the email is a VIP
	 * Support email alias.
	 *
	 * Since the email aliases are fake, they don't have a Gravatar.
	 * Instead of showing the mystery man, get the Gravatar for the
	 * real `VIP_SUPPORT_EMAIL_ADDRESS` email address.
	 *
	 * @param string $url The Gravatar url.
	 * @param mixed $id_or_email The users ID, email, or a WP_User object.
	 * @param array $args Arguments passed to get_avatar_url(), after processing.
	 *
	 * @return string
	 */
	public function filter_vip_support_email_gravatars( $url, $id_or_email, $args ) {

		if ( ! is_admin() ) {
			return $url;
		}

		if ( is_numeric( $id_or_email ) ) {
			// Get the user's email address.
			$user = get_user_by( 'id', $id_or_email );
			if ( false !== $user ) {
				$user_email = $user->user_email;
			}
		} elseif ( is_string( $id_or_email ) ) {
			// Since we already have user email, get WP_User object.
			$user_email = $id_or_email;
			$user       = get_user_by( 'email', $id_or_email );
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_email = $id_or_email->user_email;
			$user       = $id_or_email;
		}

		if ( isset( $user_email ) && self::is_a8c_email( $user_email ) && ( isset( $user->ID ) && $this->has_vip_support_meta( $user->ID ) ) ) {
			return self::VIP_SUPPORT_EMAIL_ADDRESS_GRAVATAR . '?d=mm&r=g&s=' . $args['size'];
		}

		return $url;
	}

	/**
	 * Don't show the admin email confirmation screen to vip support users.
	 *
	 * @param string           $redirect_to The redirect destination URL.
	 * @param string           $requested   The requested redirect destination URL passed as a parameter.
	 * @param WP_User|WP_Error $user        WP_User object if login was successful, WP_Error object otherwise.
	 *
	 * @return $redirect_to
	 */
	public function disable_admin_email_check( $redirect_to, $requested, $user ) {
		if ( ! is_wp_error( $user ) && $this->has_vip_support_meta( $user->ID ) ) {
			add_filter( 'admin_email_check_interval', '__return_zero' );
		}

		return $redirect_to;
	}

	/**
	 * Hooks the user_register action to determine if we're registering an
	 * A12n, and so need an email verification. Also checks if the registered
	 * user cannot be set to VIP Support role (as not an A12n).
	 *
	 * When a user is registered we reset VIP Support role to inactive, then
	 * wait until they recover their password to mark their role as active.
	 *
	 * If they do not go through password recovery then we send the verification
	 * email when they first log in.
	 *
	 * @param int $user_id The ID of the user which has been registered.
	 */
	public function action_user_register( $user_id ) {
		$user = new WP_User( $user_id );
		if ( self::is_a8c_email( $user->user_email ) && self::user_has_vip_support_role( $user->ID ) ) {
			$this->demote_user_from_vip_support_to( $user->ID, Role::VIP_SUPPORT_INACTIVE_ROLE );
			$this->registering_a11n = true;
			// @TODO Abstract this into an UNVERIFY method
			$this->mark_user_email_unverified( $user_id );
			$this->send_verification_email( $user_id );
		} elseif ( self::MSG_BLOCK_UPGRADE_NON_A11N == $this->message_replace ) {
				$this->message_replace = self::MSG_BLOCK_NEW_NON_VIP_USER;
		}
	}

	/**
	 * Hooks the profile_update action to delete the email verification meta
	 * when the user's email address changes.
	 *
	 * @param int $user_id The ID of the user whose profile was just updated
	 */
	public function action_profile_update( $user_id ) {
		$user           = new WP_User( $user_id );
		$verified_email = get_user_meta( $user_id, self::META_EMAIL_VERIFIED, true );
		if ( $user->user_email !== $verified_email && self::user_has_vip_support_role( $user_id ) ) {
			$this->demote_user_from_vip_support_to( $user->ID, Role::VIP_SUPPORT_INACTIVE_ROLE );
			$this->message_replace = self::MSG_DOWNGRADE_VIP_USER;
			delete_user_meta( $user_id, self::META_EMAIL_VERIFIED );
			delete_user_meta( $user_id, self::META_VERIFICATION_DATA );
			if ( self::user_has_vip_support_role( $user_id ) || self::user_has_vip_support_role( $user_id, false ) ) {
				$this->send_verification_email( $user_id );
			}
		}
	}

	/**
	 * Hooks the parse_request action to do any email verification.
	 *
	 * @TODO Abstract all the verification stuff into a utility method for clarity
	 *
	 */
	public function action_parse_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::GET_EMAIL_VERIFY ] ) || ! isset( $_GET[ self::GET_EMAIL_USER_LOGIN ] ) ) {
			return;
		}

		$rebuffal_title   = __( 'Verification failed', 'vip-support' );
		$rebuffal_message = __( 'This email verification link is not for your account, was not recognised, has been invalidated, or has already been used.', 'vip-support' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_login = sanitize_text_field( $_GET[ self::GET_EMAIL_USER_LOGIN ] );
		$user       = get_user_by( 'login', $user_login );
		if ( ! $user ) {
			// 403 Forbidden – The server understood the request, but is refusing to fulfill it.
			// Authorization will not help and the request SHOULD NOT be repeated.
			wp_die( esc_html( $rebuffal_message ), esc_html( $rebuffal_title ), array( 'response' => 403 ) );
		}

		// We only want the user who was sent the email to be able to verify their email
		// (i.e. not another logged in or anonymous user clicking the link).
		// @FIXME: Should we expire the link at this point, so an attacker cannot iterate the IDs?
		if ( get_current_user_id() != $user->ID ) {
			wp_die( esc_html( $rebuffal_message ), esc_html( $rebuffal_title ), array( 'response' => 403 ) );
		}

		if ( ! self::is_a8c_email( $user->user_email ) ) {
			wp_die( esc_html( $rebuffal_message ), esc_html( $rebuffal_title ), array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPressVIPMinimum.Constants.RestrictedConstants.UsingRestrictedConstant
		if ( ! A8C_PROXIED_REQUEST ) {
			$proxy_rebuffal_title   = __( 'Please proxy', 'vip-support' );
			$proxy_rebuffal_message = __( 'Your IP is not special enough, please proxy.', 'vip-support' );
			wp_die( esc_html( $proxy_rebuffal_message ), esc_html( $proxy_rebuffal_title ), array( 'response' => 403 ) );
		}

		$stored_verification_code = $this->get_user_email_verification_code( $user->ID );
		$hash_sent                = (string) sanitize_text_field( $_GET[ self::GET_EMAIL_VERIFY ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$check_hash = $this->create_check_hash( get_current_user_id(), $stored_verification_code, $user->user_email );

		if ( ! hash_equals( $check_hash, $hash_sent ) ) {
			wp_die( esc_html( $rebuffal_message ), esc_html( $rebuffal_title ), array( 'response' => 403 ) );
		}

		// It's all looking good. Verify the email.
		$this->mark_user_email_verified( $user->ID, $user->user_email );

		// If the user is an A12n, add them to the support role
		// Only promotes the user if from the inactive user role
		if ( self::is_a8c_email( $user->user_email ) ) {
			$this->promote_user_to_vip_support( $user->ID );
		}

		// translators: 1 - email address
		$message = sprintf( __( 'Your email has been verified as %s', 'vip-support' ), $user->user_email );
		$title   = __( 'Verification succeeded', 'vip-support' );
		wp_die( esc_html( $message ), esc_html( $title ), array( 'response' => 200 ) );
	}

	/**
	 * Hooks the removable_query_args filter to add our arguments to those
	 * tidied up by Javascript so the user sees nicer URLs.
	 *
	 * @param array $args An array of URL parameter names which are tidied away
	 *
	 * @return array An array of URL parameter names which are tidied away
	 */
	public function filter_removable_query_args( $args ) {
		$args[] = self::GET_TRIGGER_RESEND_VERIFICATION;
		return $args;
	}

	/**
	 * Hooks the wp_login action to make any verified VIP Support user
	 * a Super Admin!
	 *
	 * @param $_user_login The login for the logging in user
	 * @param WP_User $user The WP_User object for the logging in user
	 *
	 * @return void
	 */
	public function action_wp_login( $_user_login, $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gettype() is safe
			trigger_error( sprintf( '$user must be an instance of WP_User, %s given', gettype( $user ) ), E_USER_WARNING );
			return;
		}

		if ( ! is_multisite() ) {
			return;
		}
		// If the user:
		// * Is an inactive support user
		// * Is a super admin
		// …revoke their powers
		if ( self::user_has_vip_support_role( $user->ID, false ) && is_super_admin( $user->ID ) ) {
			require_once ABSPATH . '/wp-admin/includes/ms.php';
			revoke_super_admin( $user->ID );
			return;
		}
		if ( ! self::user_has_vip_support_role( $user->ID ) ) {
			// If the user is a super admin, but has been demoted to
			// the inactive VIP Support role, we should remove
			// their super powers
			if ( is_super_admin( $user->ID ) && self::user_has_vip_support_role( $user->ID, false ) ) {
				// This user is NOT VIP Support, remove
				// their powers forthwith
				require_once ABSPATH . '/wp-admin/includes/ms.php';
				revoke_super_admin( $user->ID );
			}
			return;
		}
		if ( ! $this->user_has_verified_email( $user->ID ) ) {
			return;
		}
		if ( is_super_admin( $user->ID ) ) {
			return;
		}
		if ( ! is_super_admin( $user->ID ) ) {
			// This user is VIP Support, verified, let's give them
			// great power and responsibility
			require_once ABSPATH . '/wp-admin/includes/ms.php';
			grant_super_admin( $user->ID );
		}
	}

	// UTILITIES
	// =========

	/**
	 * Send a user an email with a verification link for their current email address.
	 *
	 * See the action_parse_request for information about the hash
	 * @see VipSupportUser::action_parse_request
	 *
	 * @param int $user_id The ID of the user to send the email to
	 */
	protected function send_verification_email( $user_id ) {
		// @FIXME: Should the verification code expire?


		$verification_code = $this->get_user_email_verification_code( $user_id );

		$user = new WP_User( $user_id );
		$hash = $this->create_check_hash( $user_id, $verification_code, $user->user_email );

		$hash              = urlencode( $hash );
		$user_id           = absint( $user_id );
		$verification_link = add_query_arg( array(
			self::GET_EMAIL_VERIFY     => urlencode( $hash ),
			self::GET_EMAIL_USER_LOGIN => urlencode( $user->user_login ),
		), site_url() );

		$user = new WP_User( $user_id );

		$message  = __( 'Dear Automattician,', 'vip-support' );
		$message .= PHP_EOL . PHP_EOL;
		// translators: 1: blog name, 2: blog URL
		$message .= sprintf( __( 'You need to verify your Automattic email address for your user on %1$s (%2$s). If you are expecting this, please click the link below to verify your email address:', 'vip-support' ), get_bloginfo( 'name' ), site_url() );
		$message .= PHP_EOL;
		$message .= esc_url_raw( $verification_link );
		$message .= PHP_EOL . PHP_EOL;
		$message .= __( 'If you have any questions, please contact the WordPress.com VIP Support Team.' );

		// translators: 1 - site name
		$subject = sprintf( __( 'Email verification for %s', 'vip-support' ), get_bloginfo( 'name' ) );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Check if a user has verified their email address.
	 *
	 * @param int $user_id The ID of the user to check
	 *
	 * @return bool True if the user has a verified email address, otherwise false
	 */
	protected function user_has_verified_email( $user_id ) {
		$user           = new WP_User( $user_id );
		$verified_email = get_user_meta( $user_id, self::META_EMAIL_VERIFIED, true );
		return ( $user->user_email == $verified_email );
	}

	/**
	 * Create and return a URL with a parameter which will trigger the
	 * resending of a verification email.
	 *
	 * @return string A URL with a parameter to trigger a verification email
	 */
	protected function get_trigger_resend_verification_url() {
		return add_query_arg( array( self::GET_TRIGGER_RESEND_VERIFICATION => '1' ) );
	}

	/**
	 * Is a provided string an email address using an A8c domain.
	 *
	 * @param string $email An email address to check
	 *
	 * @return bool True if the string is an email with an A8c domain
	 */
	public static function is_a8c_email( $email ) {
		if ( ! is_email( $email ) ) {
			return false;
		}
		list( , $domain ) = explode( '@', $email, 2 );
		$a8c_domains      = array(
			'a8c.com',
			'automattic.com',
			'matticspace.com',
		);
		if ( in_array( $domain, $a8c_domains ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Is a provided email address allowed to be a support user on this site?
	 *
	 * On certain sites with tight access restrictions, only certain support users are allowed
	 *
	 * @param string $email An email address to check
	 *
	 * @return bool True if the string is an allowed support user
	 */
	public function is_allowed_email( $email ) {
		// If no override is defined, allow
		if ( ! defined( 'VIP_SUPPORT_USER_ALLOWED_EMAILS' ) ) {
			return true;
		}

		$allowed_emails = constant( 'VIP_SUPPORT_USER_ALLOWED_EMAILS' );

		// Incorrectly formatted constant, fail fast + closed
		if ( ! is_array( $allowed_emails ) ) {
			return false;
		}

		// If the override _is_ present, then the user is only allowed if their email is in the array
		return in_array( $email, $allowed_emails, true );
	}

	/**
	 * Determine if a given user has been validated as an Automattician
	 *
	 * Checks their email address as well as their email address verification status. Additionally
	 * checks to ensure the user is allowed, in case the site has restricted which accounts can be used
	 *
	 * @TODO Check the A11n is also proxxied
	 *
	 * @param int The WP User id to check
	 * @return bool Boolean indicating if the account is a valid Automattician
	 */
	public static function is_verified_automattician( $user_id ) {
		$user = new WP_User( $user_id );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		$instance = self::init();

		$is_a8c_email     = self::is_a8c_email( $user->user_email );
		$is_allowed_email = $instance->is_allowed_email( $user->user_email );
		$email_verified   = $instance->user_has_verified_email( $user->ID );

		return ( $is_a8c_email && $is_allowed_email && $email_verified );
	}

	public static function has_vip_support_meta( $user_id ) {
		$user_meta = get_user_meta( $user_id, self::VIP_SUPPORT_USER_META_KEY, true );

		if ( empty( $user_meta ) ) {
			return false;
		}

		return true;
	}

	public static function user_has_vip_support_role( $user_id, $active_role = true ) {
		$user = new WP_User( $user_id );

		if ( ! $user || ! $user->ID ) {
			return false;
		}

		$wp_roles = wp_roles();

		// Filter out caps that are not role names and assign to $user_roles
		if ( is_array( $user->caps ) ) {
			$user_roles = array_filter( array_keys( $user->caps ), array( $wp_roles, 'is_role' ) );
		}

		if ( false === $active_role ) {
			return in_array( Role::VIP_SUPPORT_INACTIVE_ROLE, $user_roles, true );
		}

		return in_array( Role::VIP_SUPPORT_ROLE, $user_roles, true );
	}

	/**
	 * Provide a randomly generated verification code to share via
	 * the email verification link.
	 *
	 * Stored in the same serialised user meta value:
	 * * The verification code
	 * * The email the verification code was generated against
	 * * The last time this method was touched, so we can calculate expiry
	 *   in the future if we want to
	 *
	 * @param int $user_id The ID of the user to get the verification code for
	 *
	 * @return string A random hex string
	 */
	protected function get_user_email_verification_code( $user_id ) {
		$generate_new_code = false;
		$user              = get_user_by( 'id', $user_id );

		$verification_data = get_user_meta( $user_id, self::META_VERIFICATION_DATA, true );
		if ( ! $verification_data ) {
			$verification_data = array(
				'touch' => time(), // GPL timestamp
			);
			$generate_new_code = true;
		}

		if ( $verification_data['email'] != $user->user_email ) {
			$generate_new_code = true;
		}

		if ( $generate_new_code ) {
			$verification_data['code']  = bin2hex( openssl_random_pseudo_bytes( 16 ) );
			$verification_data['touch'] = time();
		}

		// Refresh the email, in case it changed since we created the meta
		// (this can happen if a user changes their email 1+ times)
		$verification_data['email'] = $user->user_email;

		update_user_meta( $user_id, self::META_VERIFICATION_DATA, $verification_data );

		return $verification_data['code'];
	}

	/**
	 * The hash sent in the email verification link is composed of the user ID, a verification code
	 * generated and stored when the email was sent (a random string), and the user email. The idea
	 * being that each verification link is tied to a user AND a particular email address, so a link
	 * does not work if the user has subsequently changed their email and does not work for another
	 * logged in or anonymous user.
	 *
	 * @param int $user_id The ID of the user to generate the hash for
	 * @param string $verification_code A string of random characters
	 * @param string $user_email The email of the user to generate the hash for
	 *
	 * @return string The check hash for the values passed
	 */
	protected function create_check_hash( $user_id, $verification_code, $user_email ) {
		return wp_hash( $user_id . $verification_code . $user_email );
	}

	/**
	 * @TODO Write a method description
	 *
	 * @param int $user_id The ID of the user to mark as having a verified email
	 * @param string $user_email The email which has been verified
	 */
	public function mark_user_email_verified( $user_id, $user_email ) {
		update_user_meta( $user_id, self::META_EMAIL_VERIFIED, $user_email );
		delete_user_meta( $user_id, self::META_VERIFICATION_DATA );
		delete_user_meta( $user_id, self::META_EMAIL_NEEDS_VERIFICATION );
	}

	/**
	 * @param int $user_id The ID of the user to mark as NOT (any longer) having a verified email
	 */
	protected function mark_user_email_unverified( $user_id ) {
		update_user_meta( $user_id, self::META_EMAIL_VERIFIED, false );
		update_user_meta( $user_id, self::META_EMAIL_NEEDS_VERIFICATION, false );
	}

	/**
	 * Promote an inactive support user
	 *
	 * @param $user_id
	 * @return bool
	 */
	protected function promote_user_to_vip_support( $user_id ) {
		$user = new WP_User( $user_id );

		// Don't promote a user unless that person is an inactive support user
		if ( ! self::user_has_vip_support_role( $user->ID, false ) ) {
			return false;
		}

		$user->set_role( Role::VIP_SUPPORT_ROLE );
		if ( is_multisite() ) {
			require_once ABSPATH . '/wp-admin/includes/ms.php';
			grant_super_admin( $user_id );
		}
		update_user_meta( $user->ID, $GLOBALS['wpdb']->get_blog_prefix() . 'user_level', 10 );

		return true;
	}

	/**
	 * Demote a user to a
	 *
	 * @param $user_id
	 * @param $revert_role_to
	 */
	protected function demote_user_from_vip_support_to( $user_id, $revert_role_to ) {
		$user = new WP_User( $user_id );
		$user->set_role( $revert_role_to );
		if ( is_multisite() ) {
			require_once ABSPATH . '/wp-admin/includes/ms.php';
			revoke_super_admin( $user_id );
		}
	}

	/**
	 * Add a new support user for the given request
	 *
	 * Will delete an existing user with the same user_login as is to be created
	 *
	 * @param array $user_data Array of data to create user.
	 * @return int|WP_Error
	 */
	public static function add( $user_data ) {
		// A user with this email address may already exist, in which case
		// we should update that user record
		$user = get_user_by( 'email', $user_data['user_email'] );

		/** Include admin user functions to get access to wp_delete_user() */
		require_once ABSPATH . 'wp-admin/includes/user.php';

		// If the user already exists with a different login, change their email.
		// This is to avoid conflicts between the old user and new one we're creating.
		// We used to delete the users but that could lead to lost data.
		if ( false !== $user && $user_data['user_login'] !== $user->user_login ) {
			add_filter( 'send_password_change_email', '__return_false' );
			$updated_email_for_old_user = str_replace( '@', '+old@', $user->user_email );
			wp_update_user( [
				'ID'         => $user->ID,
				'user_email' => $updated_email_for_old_user,
			] );
			remove_filter( 'send_password_change_email', '__return_false' );

			// Force create new user if no existing user by login
			$user = false;
		}

		if ( false === $user ) {
			// Re-try to see if there is an existing user by login.
			$user = get_user_by( 'login', $user_data['user_login'] );
		}

		if ( false === $user ) {
			$user_id = wp_insert_user( $user_data );
		} else {
			$user_data['ID'] = $user->ID;
			add_filter( 'send_password_change_email', '__return_false' );
			$user_id = wp_update_user( $user_data );
			remove_filter( 'send_password_change_email', '__return_false' );
		}

		// It's possible the user update/insert will fail, we need to log this
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		remove_action( 'set_user_role', array( self::init(), 'action_set_user_role' ), 10 );
		$user = new WP_User( $user_id );
		add_action( 'set_user_role', array( self::init(), 'action_set_user_role' ), 10, 3 );

		self::init()->mark_user_email_verified( $user->ID, $user->user_email );
		$user->set_role( Role::VIP_SUPPORT_ROLE );

		update_user_meta( $user_id, self::VIP_SUPPORT_USER_META_KEY, time() );

		// If this is a multisite, commence super powers!
		if ( is_multisite() ) {
			grant_super_admin( $user->ID );
		}

		return $user_id;
	}

	/**
	 * Remove a VIP Support user
	 *
	 * @param mixed $user_id User ID, login, or email. See `get_user_by()`.
	 * @param string $by What to search for user by. See `get_user_by()`.
	 * @return bool|WP_Error
	 */
	public static function remove( $user_id, $by = 'email' ) {
		// Let's find the user
		$user = get_user_by( $by, $user_id );

		if ( false === $user ) {
			return new WP_Error( 'invalid-user', 'User does not exist' );
		}

		// Never remove the machine user.
		if (
			( defined( 'WPCOM_VIP_MACHINE_USER_LOGIN' ) && \WPCOM_VIP_MACHINE_USER_LOGIN === $user->user_login ) ||
			( defined( 'WPCOM_VIP_MACHINE_USER_EMAIL' ) && \WPCOM_VIP_MACHINE_USER_EMAIL === $user->user_email )
		) {
			return new WP_Error( 'not-removing-machine-user', 'WPCOM VIP machine user cannot be removed!' );
		}

		// Check user is a VIP Support user,
		// and bail out if not
		$is_vip_support_user = self::has_vip_support_meta( $user->ID );
		if ( ! $is_vip_support_user ) {
			return new WP_Error( 'not-support-user', 'Specified user is not a support user' );
		}

		/** Include admin user functions to get access to wp_delete_user() */
		require_once ABSPATH . 'wp-admin/includes/user.php';

		// If the user already exists, we should delete and recreate them,
		// it's the only way to be sure we get the right user_login
		if ( is_multisite() ) {
			revoke_super_admin( $user->ID );
			return wpmu_delete_user( $user->ID );
		}

		return wp_delete_user( $user->ID, null );
	}

	/**
	 * Remove support users created more than a day ago
	 *
	 * @return array
	 */
	public static function remove_stale_support_users() {
		$support_users = get_users( array(
			'meta_key' => self::VIP_SUPPORT_USER_META_KEY,
			'fields'   => [ 'ID', 'user_registered', 'user_email', 'user_login' ],
		) );

		if ( empty( $support_users ) ) {
			return array();
		}

		$processed_ids = [];

		// Report the users removed.
		$removed = array();

		// Remove support user after 8 hours (about 1 shift).
		$threshold = strtotime( '-8 hours' );

		foreach ( $support_users as $user ) {
			if ( in_array( $user->ID, $processed_ids, true ) ) {
				continue;
			}

			$processed_ids[] = $user->ID;

			// Only remove expired users.
			$created = strtotime( $user->user_registered );
			if ( $created > $threshold ) {
				continue;
			}

			$rm = self::remove( $user->ID, 'id' );

			$removed[] = array(
				'ID'      => $user->ID,
				'email'   => $user->user_email,
				'login'   => $user->user_login,
				'removed' => $rm,
			);
		}

		return $removed;
	}

	/**
	 * Remove stale users via cron
	 */
	public static function do_cron_cleanup() {
		// TODO: search for some meta in case someone changes roles?

		$stale = self::remove_stale_support_users();

		if ( ! empty( $stale ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_var_export
			error_log( "VIP Support user removals attempted: \n" . var_export( compact( 'stale' ), true ) );
		}
	}
}

User::init();
