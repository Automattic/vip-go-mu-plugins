<?php
/**
 * Plugin Name: VIP Force Two Step
 * Description: Force Two Step Auth
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// https://github.com/georgestephanis/two-factor/issues/78
add_filter( 'two_factor_providers', function( $p ) {
        unset( $p[ 'Two_Factor_FIDO_U2F' ] );
        return $p;
});

function sso_is_enabled() {
	return class_exists( 'Jetpack' ) && Jetpack::is_active() && Jetpack::is_module_active( 'sso' );
}

function wpcom_vip_force_twostep() {
	return ! A8C_PROXIED_REQUEST && ! wpcom_vip_plugin_is_loaded( 'shared-plugins/jetpack-force-2fa' ) && class_exists( 'Two_Factor_Core' ) && ! Two_Factor_Core::is_user_using_two_factor();
}

function wpcom_enable_two_factor_plugin() {
	if ( ! wpcom_vip_plugin_is_loaded( 'shared-plugins/jetpack-force-2fa' ) && ! sso_is_enabled() ) {
		wpcom_vip_load_plugin( 'two-factor' );
	} else if ( sso_is_enabled() ) {
		wpcom_vip_load_plugin( 'jetpack-force-2fa' );
		remove_action( 'wp_login', array( 'Two_Factor_Core', 'wp_login' ) );
	}
}
add_action( 'setup_theme', 'wpcom_enable_two_factor_plugin' );

/**
 * Twostep: Filter Caps
 *
 * Remove caps for users without Twostep enabled
 */
function wpcom_vip_twostep_filter_caps( $caps ) {
	$contributor = array_keys( get_role( 'contributor' )->capabilities );

	if ( wpcom_vip_force_twostep() ) {
		foreach( $caps as $cap ) {
			if ( ! in_array( $cap, $contributor ) ) {
				return array( 'do_not_allow' );
			}
		}
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'wpcom_vip_twostep_filter_caps' );

function wpcom_vip_twostep_admin_notice() {
	if ( ! wpcom_vip_force_twostep() ) {
		return;
	}
	?>
	<div class="error">
		<p><a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">Two Step Authentication</a> is required to publish to this site.</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'wpcom_vip_twostep_admin_notice' );

