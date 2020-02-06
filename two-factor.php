<?php
/**
 * Plugin Name: VIP Force Two Factor
 * Description: Force Two Factor Authentication for stronger security.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// Custom list of providers
require_once __DIR__ . '/wpcom-vip-two-factor/set-providers.php';

// Detect if the current user is logged in via Jetpack SSO
require_once __DIR__ . '/wpcom-vip-two-factor/is-jetpack-sso.php';

// Handle 2fa for API requests
add_filter( 'two_factor_user_api_login_enable', function( $allow_2fa_bypass, $user_id ) {
	// Allow API requests for a subset of environments for now.
	if ( defined( 'VIP_2FA_ALLOW_API_LOGIN_ENV_IDS' )
		&& is_array( VIP_2FA_ALLOW_API_LOGIN_ENV_IDS )
		&& in_array( FILES_CLIENT_SITE_ID, VIP_2FA_ALLOW_API_LOGIN_ENV_IDS, true ) ) {

		Automattic\VIP\Stats\send_pixel( [
			'vip-go-2fa-api-allowed' => sprintf( '%d-%s', FILES_CLIENT_SITE_ID, sanitize_key( $_SERVER['HTTP_USER_AGENT'] ) ),
		] );

		$user = get_userdata( $user_id );
		$user_login = $user ? $user->user_login : sprintf( 'user_id #%s', $user_id );

		trigger_error( sprintf(
			'The request to %s %s%s (from %s + %s + %s) may be blocked soon because of 2fa restrictions #2fa-api-block.',
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['HTTP_HOST'],
			$_SERVER['REQUEST_URI'],
			$user_login,
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_USER_AGENT']
		), E_USER_WARNING );

		return true;
	}

	// Track stats around how frequently we're hitting this
	Automattic\VIP\Stats\send_pixel( [
		'vip-go-2fa-api-blocked-by-site' => FILES_CLIENT_SITE_ID,
		'vip-go-2fa-api-blocked-by-ua' => sanitize_key( $_SERVER['HTTP_USER_AGENT'] ), 
	] );

	// Do not allow API requests for users with 2fa enabled.
	return false;
}, 1, 2 ); // Allow overrides at later priorities

function wpcom_vip_should_force_two_factor() {

	// Don't force 2FA by default in local environments
	if ( ! WPCOM_IS_VIP_ENV && ! apply_filters( 'wpcom_vip_is_two_factor_local_testing', false ) ) {
		return false;
	}
	
	// The proxy is the second factor for VIP Support users
	if ( true === A8C_PROXIED_REQUEST ) {
		return false;
	}

	// The Two Factor plugin wasn't loaded for some reason.
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return false;
	}

	if ( wpcom_vip_is_current_user_using_two_factor() ) {
		return false;
	}

	// Don't force 2FA for Jetpack SSO users that have Two-step enabled
	if ( \Automattic\VIP\TwoFactor\is_jetpack_sso_two_step() ) {
		return false;
	}

	// If it's a request attempting to connect a local user to a
	// WordPress.com user via XML-RPC or REST, allow it through.
	if ( wpcom_vip_is_jetpack_authorize_request() ) {
		return false;
	}

	// Allow custom SSO solutions
	if ( wpcom_vip_use_custom_sso() ) {
		return false;
	}

	return true;
}

/**
 * Determines if the current user using 2FA.
 * 
 * Uses a static variable to store the result of Two_Factor_Core::is_user_using_two_factor()
 * by user ID to limit the number of times it needs to be called for each user to one time.
 *
 * @return bool
 */
function wpcom_vip_is_current_user_using_two_factor() {
	static $user_using_2FA = array();

	$current_user_id = get_current_user_id();
	
	if ( isset( $user_using_2FA[ $current_user_id ]  ) ) {
		return $user_using_2FA[ $current_user_id ];
	}

	$user_using_2FA[ $current_user_id ] = Two_Factor_Core::is_user_using_two_factor();

	return $user_using_2FA[ $current_user_id ];
}

function wpcom_vip_use_custom_sso() {

	$custom_sso_enabled = apply_filters( 'wpcom_vip_use_custom_sso', null );
	if( null !== $custom_sso_enabled ) {
		return $custom_sso_enabled;
	}

	// Check for OneLogin SSO
	if ( function_exists( 'is_saml_enabled' ) && is_saml_enabled() ) {
		return true;
	}

	// Check for SimpleSaml
	if ( function_exists( '\HumanMade\SimpleSaml\instance' ) && \HumanMade\SimpleSaml\instance() ) {
		return true;
	}

	return false;
}

function wpcom_vip_is_jetpack_authorize_request() {
	return (
		// XML-RPC Jetpack authorize request
		// This works with the classic core XML-RPC endpoint, but not
		// Jetpack's alternate endpoint.
		defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST
		&& isset( $_GET['for'] ) && 'jetpack' === $_GET['for']
		&& isset( $GLOBALS['wp_xmlrpc_server'], $GLOBALS['wp_xmlrpc_server']->message , $GLOBALS['wp_xmlrpc_server']->message->methodName )
		&& 'jetpack.remoteAuthorize' === $GLOBALS['wp_xmlrpc_server']->message->methodName
	) || (
		// REST Jetpack authorize request
		defined( 'REST_REQUEST' ) && REST_REQUEST
		&& isset( $GLOBALS['wp_rest_server'] )
		&& wpcom_vip_is_jetpack_authorize_rest_request()
	);
}

/**
 * Setter/Getter to keep track of whether the current request is a REST
 * API request for /jetpack/v4/remote_authorize request that connects a
 * WordPress.com user to a local user.
 */
function wpcom_vip_is_jetpack_authorize_rest_request( $set = null ) {
	static $is_jetpack_authorize_rest_request = false;
	if ( ! is_null( $set ) ) {
		$is_jetpack_authorize_rest_request = $set;
	}

	return $is_jetpack_authorize_rest_request;
}

/**
 * Hooked to the `rest_request_before_callbacks` filter to keep track of
 * whether the current request is a REST API request for
 * /jetpack/v4/remote_authorize request that connects WordPress.com user
 * to a local user.
 * @return unmodified - it's attached to a filter.
 */
function wpcom_vip_is_jetpack_authorize_rest_request_hook( $response, $handler ) {
	if ( isset( $handler['callback'] ) && 'Jetpack_Core_Json_Api_Endpoints::remote_authorize' === $handler['callback'] ) {
		wpcom_vip_is_jetpack_authorize_rest_request( true );
	}
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'wpcom_vip_is_jetpack_authorize_rest_request_hook', 10, 2 );

function wpcom_vip_is_two_factor_forced() {
	if ( ! wpcom_vip_should_force_two_factor() ) {
		return false;
	}

	return apply_filters( 'wpcom_vip_is_two_factor_forced', false );
}

function wpcom_vip_enforce_two_factor_plugin() {
	if ( is_user_logged_in() ) {
		$cap = apply_filters( 'wpcom_vip_two_factor_enforcement_cap', 'manage_options' );
		$limited = current_user_can( $cap );
		
		// Calculate current_user_can outside map_meta_cap to avoid callback loop
		add_filter( 'wpcom_vip_is_two_factor_forced', function() use ( $limited ) {
			return $limited;
		}, 9 );

		add_action( 'admin_notices', 'wpcom_vip_two_factor_admin_notice' );
		add_filter( 'map_meta_cap', 'wpcom_vip_two_factor_filter_caps', 0, 4 );
	}
}

add_action( 'muplugins_loaded', 'wpcom_enable_two_factor_plugin' );
function wpcom_enable_two_factor_plugin() {
	$enable_two_factor = apply_filters( 'wpcom_vip_enable_two_factor', true );
	if ( true !== $enable_two_factor ) {
		return;	
	}

	wpcom_vip_load_plugin( 'two-factor' );
	add_action( 'set_current_user', 'wpcom_vip_enforce_two_factor_plugin' );
}

/**
 * Filter Caps
 *
 * Remove caps for users without two-factor enabled so they are treated as a Contributor.
 */
function wpcom_vip_two_factor_filter_caps( $caps, $cap, $user_id, $args ) {
	// If the machine user is not defined or the current user is not the machine user, don't filter caps.
	if ( wpcom_vip_is_two_factor_forced() && ( ! defined( 'WPCOM_VIP_MACHINE_USER_ID' ) || $user_id !== WPCOM_VIP_MACHINE_USER_ID ) ) {
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

function wpcom_vip_should_show_notice_on_current_screen() {
	$screen = get_current_screen();

	// Don't show on the "Edit Post" screen as it interferes with the Block Editor.
	if ( $screen->is_block_editor() ) {
		return false;
	}

	return true;
}

function wpcom_vip_two_factor_admin_notice() {
	if ( ! wpcom_vip_is_two_factor_forced() ) {
		return;
	}

	if ( ! wpcom_vip_should_show_notice_on_current_screen() ) {
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
