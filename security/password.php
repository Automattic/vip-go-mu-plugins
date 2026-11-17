<?php

namespace Automattic\VIP\Security;

use WP_Error;
use WP_REST_Request;
use WP_REST_Users_Controller;

add_action( 'user_profile_update_errors', __NAMESPACE__ . '\validate_current_password', 1, 3 );

/**
 * Validate current password in submitted user profile.
 *
 * @param WP_Error $errors Error object passed by reference
 * @param bool $update Whether this is a user update
 * @param object $user User object passed by reference
 *
 * @return void
 */
function validate_current_password( WP_Error &$errors, bool $update, &$user ) {
	if ( ! $update || ! is_profile_page() ) {
		return;
	}

	check_admin_referer( 'update-user_' . $user->ID );

	if ( empty( $_POST['pass1'] ) ) {
		return;
	}

	if ( empty( $_POST['current_pass'] ) ) {
		$errors->add( 'empty_current_password', '<strong>Error</strong>: Please enter your current password.', array( 'form-field' => 'current_pass' ) );
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- TODO: do we need to wp_unslash() it first?
	$auth = wp_authenticate( $user->user_login, $_POST['current_pass'] );

	// Two Factor blocks auth cookies for the rest of the request once `authenticate` passes for a 2FA user.
	// Lift the block so core can refresh the cookies after the password change instead of logging the user out.
	remove_filter( 'send_auth_cookies', '__return_false', PHP_INT_MAX );

	if ( is_wp_error( $auth ) ) {
		$errors->add( 'wrong_current_password', '<strong>Error</strong>: The entered current password is not correct.', array( 'form-field' => 'current_pass' ) );
	}
}

add_filter( 'rest_request_before_callbacks', __NAMESPACE__ . '\block_app_password_credential_changes', 10, 3 );

/**
 * Stop application passwords from changing their own user's password or email, which would let the holder sign in as the user.
 *
 * @param mixed           $response Result to send to the client.
 * @param array           $handler  Route handler used for the request.
 * @param WP_REST_Request $request  Request used to generate the response.
 *
 * @return mixed
 */
function block_app_password_credential_changes( $response, $handler, $request ) {
	// Not rest_get_authenticated_app_password(): core clears it on any failed authentication later in the request.
	$callback = $handler['callback'] ?? null;
	if ( ! did_action( 'application_password_did_authenticate' ) || ! is_array( $callback ) || ! $callback[0] instanceof WP_REST_Users_Controller ) {
		return $response;
	}

	$user                = wp_get_current_user();
	$is_own_account      = 'update_current_item' === $callback[1] || ( 'update_item' === $callback[1] && (int) $request['id'] === $user->ID );
	$changes_credentials = isset( $request['password'] ) || ( isset( $request['email'] ) && $request['email'] !== $user->user_email );

	if ( $is_own_account && $changes_credentials ) {
		return new WP_Error(
			'rest_cannot_edit_credentials',
			'Your password and email address cannot be changed with an application password. Sign in to WordPress to change them.',
			array( 'status' => 403 )
		);
	}

	return $response;
}

add_action( 'show_user_profile', __NAMESPACE__ . '\add_current_password_field' );

/**
 * Render input field for current password.
 *
 * @return void
 */
function add_current_password_field() {
	?>
	<table id="nojs-current-pass" class="form-table" role="presentation">
		<tr>
			<th><label for="current_pass">Current Password</label></th>
			<td>
				<div id="current-password-confirm">
					<input type="password" name="current_pass" id="current_pass" placeholder="<?php esc_attr_e( 'Current Password' ); ?>" class="regular-text" value="" autocomplete="off" />
					<p class="description">
						Please type your <strong>current password</strong> to update it.
					</p>
				</div>

			</td>
		</tr>
	</table>
	<?php 
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\reposition_current_password_field' );

/**
 * Custom JS to reposition the current password field in admin.
 *
 * @return void
 */
function reposition_current_password_field() {
	if ( is_profile_page() ) {
		wp_enqueue_script( 'vip-security-password', plugins_url( '/js/password.js', __FILE__ ), array(), '1.0', true );
	}
}

/**
 * Whether the user is editing their own profile in admin: the site, network or user admin profile, or user-edit.php for themselves.
 */
function is_profile_page(): bool {
	return defined( 'IS_PROFILE_PAGE' ) && constant( 'IS_PROFILE_PAGE' );
}
