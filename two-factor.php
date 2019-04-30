<?php
/**
 * Plugin Name: VIP Force Two Factor
 * Description: Force Two Factor Authentication for stronger security.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Custom list of providers
require_once __DIR__ . '/wpcom-vip-two-factor/set-providers.php';

define( 'VIP_2FA_TIME_GATE', strtotime( '2019-05-29 18:00:00' ) );
define( 'VIP_IS_AFTER_2FA_TIME_GATE', time() > VIP_2FA_TIME_GATE );

function wpcom_vip_is_two_factor_forced() {
	// The proxy is the second factor for VIP Support users
	if ( true === A8C_PROXIED_REQUEST ) {
		return false;
	}

	// The Two Factor plugin wasn't loaded for some reason.
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return false;
	}

	if ( Two_Factor_Core::is_user_using_two_factor() ) {
		return false;
	}

	return apply_filters( 'wpcom_vip_is_two_factor_forced', false );
}

function wpcom_vip_enforce_two_factor_plugin() {
	if ( is_user_logged_in() ) {
		$limited = current_user_can( 'edit_posts' );

		if ( VIP_IS_AFTER_2FA_TIME_GATE ) {
			// Calculate current_user_can outside map_meta_cap to avoid callback loop
			add_filter( 'wpcom_vip_is_two_factor_forced', function() use ( $limited ) {
				return $limited;
			} );
		} else if ( $limited && ! Two_Factor_Core::is_user_using_two_factor() ) {
			add_action( 'admin_notices', 'wpcom_vip_two_factor_prep_admin_notice' );
		}

		add_action( 'admin_notices', 'wpcom_vip_two_factor_admin_notice' );
		add_filter( 'map_meta_cap', 'wpcom_vip_two_factor_filter_caps', 0, 4 );
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
function wpcom_vip_two_factor_filter_caps( $caps, $cap, $user_id, $args ) {
	if ( wpcom_vip_is_two_factor_forced() ) {
		// Use a hard-coded list of caps that give just enough access to set up 2FA
		$subscriber_caps = [
			'read',
			'level_0',
		];

		// You can edit your own user account (required to set up 2FA)
		if ( $cap === 'edit_user' && ! empty( $args ) && $user_id === $args[ 0 ] ) {
			$subscriber_caps[] = 'edit_user';
		}

		if ( ! in_array( $cap, $subscriber_caps, true ) ) {
			return array( 'do_not_allow' );
		}
	}

	return $caps;
}

function wpcom_vip_two_factor_admin_notice() {
	if ( ! wpcom_vip_is_two_factor_forced() ) {
		return;
	}

	?>
	<div id="vip-2fa-error" class="notice-error wrap clearfix" style="align-items: center;background: #ffffff;border-left-width:4px;border-left-style:solid;border-radius: 6px;display: flex;margin-top: 30px;padding: 30px;line-height: 2em;">
			<div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#ffb900;"></div>
			<div>
				<p style="font-weight:bold; font-size:16px;">
					<a href="https://wpvip.com/documentation/vip-go/two-factor-authentication-on-vip-go/">Two Factor Authentication</a> is required to edit content on this site.
				</p>

				<p>For the safety and security of this site, your account access has been downgraded. Please enable two-factor authentication to restore your access.</p>

				<p>
					<a href="<?php echo esc_url( admin_url( 'profile.php#two-factor-options' ) ); ?>" class="button button-primary">
						Enable Two-factor Authentication
					</a>

					<a href="https://wpvip.com/documentation/vip-go/two-factor-authentication-on-vip-go/" class="button" target="_blank">Learn More</a>
				</p> 
			</div>
	</div>
	<?php
}

function wpcom_vip_two_factor_prep_admin_notice() {
	if ( wpcom_vip_is_two_factor_forced() ) {
		return;
	}

	$timezone = get_option( 'timezone_string' );
	if ( ! $timezone || $timezone === '' ) {
		$timezone = 'UTC';
	}

	$date = new DateTime( "now", new DateTimeZone( $timezone ) );
	$date->setTimestamp( VIP_2FA_TIME_GATE );

	?>
	<div id="vip-2fa-warning" class="notice-warning wrap clearfix" style="align-items: center;background: #ffffff;border-left-width:4px;border-left-style:solid;border-radius: 6px;display: flex;margin-top: 30px;padding: 30px;line-height: 2em;">
			<div class="dashicons dashicons-warning" style="display:flex;float:left;margin-right:2rem;font-size:38px;align-items:center;margin-left:-20px;color:#ffb900;"></div>
			<div>
				<p style="font-weight:bold; font-size:16px;">
					Starting on <em><?php echo $date->format( 'M d, Y \a\t g:i a T' ) ?></em>, <a href="https://wpvip.com/documentation/vip-go/two-factor-authentication-on-vip-go/">Two Factor Authentication</a> will be required to edit content on this site.
				</p>

				<p>To avoid any disruption in access, please enable two-factor authentication on your account as soon as possible. Thank you for keeping your account safe and secure!</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'profile.php#two-factor-options' ) ); ?>" class="button button-primary">
						Enable Two-factor Authentication
					</a>

					<a href="https://wpvip.com/documentation/vip-go/two-factor-authentication-on-vip-go/" class="button" target="_blank">Learn More</a>
				</p> 
			</div>
	</div>
	<?php
}
