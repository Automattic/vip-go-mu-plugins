<?php
namespace Automattic\VIP\Security;

/**
 * Use a login message that does not reveal the type of login error in an attempted brute-force.
 * 
 * @param string $error Login error message.
 * 
 * @return string $error Login error message.
 * 
 * @since 1.1
 */
function use_ambiguous_login_error( $error ): string {
	global $errors;

	if ( ! is_wp_error( $errors ) ) {
		return (string) $error;
	}

	$err_codes = $errors->get_error_codes();

	$err_types = [
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'invalidcombo',
	];

	foreach ( $err_types as $err ) {
		if ( in_array( $err, $err_codes, true ) ) {
			$error = '<strong>Error</strong>: The username/email address or password is incorrect. Please try again.';
			break;
		}
	}

	return (string) $error;
}

add_filter( 'login_errors', __NAMESPACE__ . '\use_ambiguous_login_error', 99, 1 );
