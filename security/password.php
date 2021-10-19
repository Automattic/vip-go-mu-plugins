<?php

namespace Automattic\VIP\Security;

use WP_Error;

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
	if ( ! $update ) {
		return;
	}

	$screen = get_current_screen();
	if ( 'profile' != $screen->id ) {
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
	if ( is_wp_error( $auth ) ) {
		$errors->add( 'wrong_current_password', '<strong>Error</strong>: The entered current password is not correct.', array( 'form-field' => 'current_pass' ) );
	}
}

add_action( 'show_user_profile', __NAMESPACE__ . '\add_current_password_field' );

/**
 * Render input field for current password.
 *
 * @return void
 */
function add_current_password_field() { ?>
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
	$screen = get_current_screen();
	if ( 'profile' === $screen->id ) {
		wp_enqueue_script( 'vip-security-password', plugins_url( '/js/password.js', __FILE__ ), array(), '1.0', true );
	}
}
