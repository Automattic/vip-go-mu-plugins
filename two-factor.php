<?php
/**
 * Plugin Name: VIP Force Two Factor
 * Description: Force Two Factor Auth
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

add_filter( 'two_factor_providers', function( $p ) {
	if ( wpcom_vip_have_twilio_keys() ) {
		$p['Two_Factor_SMS'] = __DIR__ . '/wpcom-vip-two-factor/sms-provider.php';
	}

	// https://github.com/georgestephanis/two-factor/issues/78
	unset( $p[ 'Two_Factor_FIDO_U2F' ] );
	unset( $p[ 'Two_Factor_Dummy' ] );
	return $p;
} );

function wpcom_vip_have_twilio_keys() {
	return defined( 'TWILIO_SID' ) && ! empty( TWILIO_SID )
		&& defined( 'TWILIO_SECRET' ) && ! empty( TWILIO_SECRET );
}

function wpcom_vip_is_jetpack_sso_enabled() {
	return class_exists( 'Jetpack' ) && Jetpack::is_active() && Jetpack::is_module_active( 'sso' );
}

function wpcom_vip_force_two_factor() {
	// The proxy is the second factor for VIP Support users
	if ( true === A8C_PROXIED_REQUEST ) {
		return false;
	}

	// Shouldn't run both Jetpack 2fa and Two Factor plugins at the same time.
	if ( class_exists( 'Jetpack_Force_2FA' ) ) {
		return false;
	}

	// The Two Factor plugin wasn't loaded for some reason.
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return false;
	}

	if ( Two_Factor_Core::is_user_using_two_factor() ) {
		return false;
	}

	return apply_filters( 'wpcom_vip_force_two_factor', false );
}

function wpcom_vip_load_two_factor_plugin() {
	wpcom_vip_load_plugin( 'two-factor' );

	add_action( 'admin_notices', 'wpcom_vip_two_factor_admin_notice' );
	add_filter( 'map_meta_cap', 'wpcom_vip_two_factor_filter_caps' );
}

function wpcom_enable_two_factor_plugin() {
	if ( wpcom_vip_is_jetpack_sso_enabled() ) {
		if ( ! class_exists( 'Jetpack_Force_2FA' ) ) {
			wpcom_vip_load_plugin( 'jetpack-force-2fa' );
		}

		// Prevent lockout if we end up with both jetpack-2fa and twofactor enabled at the same time.
		if ( class_exists( 'Two_Factor_Core' ) ) {
			remove_action( 'wp_login', array( 'Two_Factor_Core', 'wp_login' ) );
		}
	} else {
		wpcom_vip_load_two_factor_plugin();
	}
}
add_action( 'setup_theme', 'wpcom_enable_two_factor_plugin' );

/**
 * Filter Caps
 *
 * Remove caps for users without two-factor enabled so they are treated as a Contributor.
 */
function wpcom_vip_two_factor_filter_caps( $caps ) {
	
	$contributor = get_role( 'contributor' );

	if ( ! empty( $contributor->capabilities ) && is_array( $contributor->capabilities ) && wpcom_vip_force_two_factor() ) {
		$contributor_capabilities = array_keys( $contributor->capabilities );
		foreach( $caps as $cap ) {
			if ( ! in_array( $cap, $contributor_capabilities ) ) {
				return array( 'do_not_allow' );
			}
		}
	}

	return $caps;
}

function wpcom_vip_two_factor_admin_notice() {
	if ( ! wpcom_vip_force_two_factor() ) {
		return;
	}
	?>
	<div class="error">
		<p><a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Two Factor Authentication</a> is required to publish to this site.</p>
	</div>
	<?php
}
