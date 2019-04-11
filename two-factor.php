<?php
/**
 * Plugin Name: VIP Force Two Factor
 * Description: Force Two Factor Authentication for stronger security.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Custom list of providers
require_once __DIR__ . '/wpcom-vip-two-factor/set-providers.php';

function wpcom_vip_force_two_factor() {
	// The proxy is the second factor for VIP Support users
	if ( true === A8C_PROXIED_REQUEST ) {
		//return false;
	}

	// The Two Factor plugin wasn't loaded for some reason.
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return false;
	}

	if ( Two_Factor_Core::is_user_using_two_factor() ) {
		return false;
	}

	return apply_filters( 'wpcom_vip_force_two_factor', true );
}

function wpcom_vip_enforce_two_factor_plugin() {
	if ( is_user_logged_in() ) {
		add_action( 'admin_notices', 'wpcom_vip_two_factor_admin_notice' );
		add_filter( 'map_meta_cap', 'wpcom_vip_two_factor_filter_caps', 0, 2 );
	}
}

add_action( 'muplugins_loaded', 'wpcom_enable_two_factor_plugin' );
function wpcom_enable_two_factor_plugin() {
	wpcom_vip_load_plugin( 'two-factor' );
	add_action( 'set_current_user', 'wpcom_vip_enforce_two_factor_plugin' );
}

/**
 * Filter Caps
 *
 * Remove caps for users without two-factor enabled so they are treated as a Contributor.
 */
function wpcom_vip_two_factor_filter_caps( $caps, $cap ) {
	if ( wpcom_vip_force_two_factor() ) {
		// Use a hard-coded list of caps that closely match a Contributor role.
		// The hard-coded list avoids issues if the Contributor role doesn't exist or is modified.
		$contributor_caps = [
			'edit_user',
			'edit_posts',
			'read',
			'level_1',
			'level_0',
			'delete_posts',
		];

		if ( ! in_array( $cap, $contributor_caps ) ) {
			return array( 'do_not_allow' );
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
