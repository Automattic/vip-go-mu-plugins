<?php
namespace Automattic\VIP\Security;

function use_ambiguous_login_error( $error ) {
	global $errors;
	$err_codes = $errors->get_error_codes();

	$err_types = [
		'invalid_username',
		'invalid_email',
		'incorrect_password',
		'invalidcombo',
	];

	foreach ( $err_types as $err ) {
		if ( in_array( $err, $err_codes, true ) ) {
			$error = '<strong>ERROR</strong>: Invalid credentials. Please try again.';
			break;
		}
	}
 
	return $error;
}
// Use a login message that does not reveal the type of login error in brute-forces.
add_filter( 'login_errors', __NAMESPACE__ . '\use_ambiguous_login_error', 99, 1 );
