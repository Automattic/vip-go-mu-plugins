<?php

/*
Plugin Name: VIP Security
Description: Various security enhancements
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

function wpcom_vip_login_limiter( $username ) {
	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
	$key1 = $ip . '|' . $username; // IP + username
	$key2 = $ip; // IP only

	// Longer TTL when logging in as admin, which we don't allow on WP.com
	wp_cache_add( $key1, 0, 'login_limit', 'admin' == $username ? HOUR_IN_SECONDS : ( MINUTE_IN_SECONDS * 5 ) );
	wp_cache_add( $key2, 0, 'login_limit',  HOUR_IN_SECONDS );
	wp_cache_incr( $key1, 1, 'login_limit' );
	wp_cache_incr( $key2, 1, 'login_limit' );
}
add_action( 'wp_login_failed', 'wpcom_vip_login_limiter' );

function wpcom_vip_login_limiter_on_success( $username, $user ) {

	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
	$key1 = $ip . '|' . $username; // IP + username
	$key2 = $ip; // IP only

	wp_cache_decr( $key1, 1, 'login_limit' );
	wp_cache_decr( $key2, 1, 'login_limit' );
}
add_action( 'wp_login', 'wpcom_vip_login_limiter_on_success', 10, 2 );

function wpcom_vip_login_limiter_authenticate( $user, $username, $password ) {
	if ( empty( $username ) && empty( $password ) )
		return $user;

	if ( $error = wpcom_vip_login_is_limited( $username ) ) {
		return $error;
	}

	return $user;
}
add_filter( 'authenticate', 'wpcom_vip_login_limiter_authenticate', 30, 3 );

function wpcom_vip_login_limit_dont_show_login_form() {
	if ( 'post' != strtolower( $_SERVER['REQUEST_METHOD'] ) || !isset( $_POST['log'] ) ) {
		return;
	}

	$username = sanitize_user( $_POST['log'] );
	if ( $error = wpcom_vip_login_is_limited( $username ) ) {
		login_header( __( 'Error' ), '', $error );
		login_footer();
		exit;
	}
}
add_action( 'login_form_login', 'wpcom_vip_login_limit_dont_show_login_form' );

function wpcom_vip_login_limit_xmlrpc_error( $error, $user ) {
	if ( is_wp_error( $user ) && 'login_limit_exceeded' == $user->get_error_code() )
		return new IXR_Error( 503, $user->get_error_message() );

	return $error;
}
add_filter( 'xmlrpc_login_error', 'wpcom_vip_login_limit_xmlrpc_error', 10, 2 );

function wpcom_vip_login_is_limited( $username ) {
	$ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );

	$key1 = $ip . '|' . $username;
	$key2 = $ip;
	$count1 = wp_cache_get( $key1, 'login_limit' );
	if ( 'admin' == $username ) {
		$threshold1 = 2;
	} else {
		$threshold1 = 5;
	}

	$count2 = wp_cache_get( $key2, 'login_limit' );
	$threshold2 = 50;

	if ( $count1 >= $threshold1 || $count2 >= $threshold2 ) {
		do_action( 'login_limit_exceeded', $username );

		return new WP_Error('login_limit_exceeded', __( 'You have exceeded the login limit.  Please wait a few minutes and try again.' ) );
	}

	return false;
}

add_action( 'setup_theme', function() {
	// Automatically load two-factor if jetpack-force-2fa is disabled
	if ( ! wpcom_vip_plugin_is_loaded( 'jetpack-force-2fa' ) && ( ! class_exists( 'Jetpack' ) || ! Jetpack::is_active() || ! Jetpack::is_module_active( 'sso' ) ) ) {
		wpcom_vip_load_plugin( 'two-factor' );
	}
});

function wpcom_vip_force_twostep() {
	return ! ( class_exists( 'Two_Factor_Core' ) && Two_Factor_Core::is_user_using_two_factor() );
}

add_filter( 'map_meta_cap', function( $caps ) {
	$contributor = array_keys( get_role( 'contributor' )->capabilities );

	if ( wpcom_vip_force_twostep() ) {
		foreach( $caps as $cap ) {
			if ( ! in_array( $cap, $contributor ) ) {
				return array( 'do_not_allow' );
			}
		}
	}

	return $caps;
});

add_action( 'admin_notices', function() {
	if ( ! wpcom_vip_force_twostep() ) {
		return;
	}
	?>
	<div class="error">
		<p><a href="<?php echo admin_url( 'profile.php' ); ?>">Two Step Authentication</a> is required to publish to this site.</p>
	</div>
	<?php
});
