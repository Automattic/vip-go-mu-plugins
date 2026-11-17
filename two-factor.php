<?php
/**
 * Plugin Name: VIP Force Two Factor
 * Description: Force Two Factor Authentication for stronger security.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS ) {
	// Two Factor is incompatible with core tests (phpunit/tests/admin/includesPlugin.php)
	return;
}

// Custom list of providers
require_once __DIR__ . '/wpcom-vip-two-factor/set-providers.php';

// Detect if the current user is logged in via Jetpack SSO
require_once __DIR__ . '/wpcom-vip-two-factor/is-jetpack-sso.php';

// Do not allow API requests from 2fa users.
add_filter( 'two_factor_user_api_login_enable', '__return_false', 1 ); // Hook in early to allow overrides

function wpcom_vip_should_force_two_factor() {

	// Don't force 2FA by default in local environments
	if ( ! WPCOM_IS_VIP_ENV && ! apply_filters( 'wpcom_vip_is_two_factor_local_testing', false ) ) {
		return false;
	}

	// The proxy is the second factor for VIP Support users
	if ( is_proxied_automattician() ) {
		return false;
	}

	// The Two Factor plugin wasn't loaded for some reason.
	if ( ! class_exists( 'Two_Factor_Core' ) ) {
		return false;
	}

	if ( apply_filters( 'wpcom_vip_is_user_using_two_factor', false ) ) {
		return false;
	}

	// Don't force 2FA for Jetpack SSO users that have Two-step enabled. Only a negative result is reused: the exemption
	// is checked against the current session every time, so it ends if the session is revoked during the request.
	if ( ( wpcom_vip_two_factor_user_state()['jetpack_sso_two_step'] ?? true ) && \Automattic\VIP\TwoFactor\is_jetpack_sso_two_step() ) {
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

function wpcom_vip_use_custom_sso() {

	$custom_sso_enabled = apply_filters( 'wpcom_vip_use_custom_sso', null );
	if ( null !== $custom_sso_enabled ) {
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
		&& isset( $_GET['for'] ) && 'jetpack' === $_GET['for']  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		&& isset( $GLOBALS['wp_xmlrpc_server'], $GLOBALS['wp_xmlrpc_server']->message, $GLOBALS['wp_xmlrpc_server']->message->methodName )
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
	$is_enforced = apply_filters( 'wpcom_vip_is_two_factor_forced', false );
	// apply an internal filter so that we can detect if a site uses the wpcom_vip_is_two_factor_forced filter
	return apply_filters( 'wpcom_vip_internal_is_two_factor_forced', $is_enforced );
}

/**
 * Getter for the current user's two-factor state on the current site.
 *
 * It's only calculated on `set_current_user` ('user') and `switch_blog` ('site', since roles differ per site), outside
 * map_meta_cap: calculating it there would loop back into map_meta_cap, and the checks are too slow to repeat on
 * every capability check.
 * see: https://github.com/Automattic/vip-go-mu-plugins/pull/1445#issuecomment-592124810
 *
 * @param string|false $calculate 'user' for a new current user, 'site' for a newly switched-to site, or false to read.
 */
function wpcom_vip_two_factor_user_state( $calculate = false ) {
	static $state = [];

	$user = wp_get_current_user();
	if ( 'user' === $calculate ) {
		// Start over even for a user seen earlier in the request, as their roles may have changed since.
		$state = [];
		if ( $user->exists() ) {
			$state = [
				'user_id'              => $user->ID,
				'using_two_factor'     => Two_Factor_Core::is_user_using_two_factor( $user->ID ),
				'jetpack_sso_two_step' => (bool) \Automattic\VIP\TwoFactor\is_jetpack_sso_two_step(),
				'limited'              => [],
			];
		}
	}

	if ( ( $state['user_id'] ?? null ) !== $user->ID ) {
		return [];
	}

	$site = $user->get_site_id();
	if ( $calculate && ! isset( $state['limited'][ $site ] ) ) {
		$state['limited'][ $site ] = false; // So VIP's own limit doesn't restrict this check.
		$state['limited'][ $site ] = current_user_can( apply_filters( 'wpcom_vip_two_factor_enforcement_cap', 'manage_options' ) );
	}

	$current            = $state;
	$current['limited'] = $state['limited'][ $site ] ?? true; // Fail closed on a site that hasn't been checked yet.

	return $current;
}

function wpcom_vip_two_factor_switch_blog() {
	wpcom_vip_two_factor_user_state( 'site' );
}

function wpcom_vip_two_factor_is_limited( $is_forced ) {
	return wpcom_vip_two_factor_user_state()['limited'] ?? $is_forced;
}

function wpcom_vip_two_factor_is_user_using_two_factor( $is_using_two_factor ) {
	return wpcom_vip_two_factor_user_state()['using_two_factor'] ?? $is_using_two_factor;
}

function wpcom_vip_enforce_two_factor_plugin() {
	wpcom_vip_two_factor_user_state( 'user' );

	if ( is_user_logged_in() ) {
		// While we could use the internal wpcom_vip_internal_is_two_factor_forced filter here, we intentionally don't to avoid taking precedence over existing customer filters.
		add_filter( 'wpcom_vip_is_two_factor_forced', 'wpcom_vip_two_factor_is_limited', 9 );
		add_filter( 'wpcom_vip_is_user_using_two_factor', 'wpcom_vip_two_factor_is_user_using_two_factor' );

		// Right after core's wp_switch_roles_and_user() (priority 1) loads the user's caps for the new site.
		add_action( 'switch_blog', 'wpcom_vip_two_factor_switch_blog', 2 );

		add_action( 'admin_notices', 'wpcom_vip_two_factor_admin_notice' );
		// Run last, so a later filter can't map a restricted cap back to one the user's role still has.
		add_filter( 'map_meta_cap', 'wpcom_vip_two_factor_filter_caps', PHP_INT_MAX, 4 );
	} else {
		// After wp_logout(), check caps as in a request that starts logged out.
		remove_filter( 'map_meta_cap', 'wpcom_vip_two_factor_filter_caps', PHP_INT_MAX );
	}
}

if ( ! defined( 'WP_INSTALLING' ) ) {
	add_action( 'muplugins_loaded', 'wpcom_enable_two_factor_plugin' );
}

function wpcom_enable_two_factor_plugin() {
	$enable_two_factor = apply_filters( 'wpcom_vip_enable_two_factor', true );
	if ( true !== $enable_two_factor ) {
		return;
	}

	// We loaded the two-factor plugin using wpcom_vip_load_plugin but that skips when skip-plugins is set.
	// Switching to require_once so it no longer gets skipped
	require_once WPVIP_MU_PLUGIN_DIR . '/shared-plugins/two-factor/two-factor.php';

	// Providers are managed in code (`two_factor_providers`). Drop the Settings → Two-Factor page and its
	// saved provider list, which could switch off SMS or every provider and lock users out.
	remove_action( 'init', 'two_factor_register_admin_hooks' );

	add_action( 'set_current_user', 'wpcom_vip_enforce_two_factor_plugin' );
}

/**
 * Filter Caps
 *
 * Remove caps for users without two-factor enabled so they are treated as a Contributor.
 */
function wpcom_vip_two_factor_filter_caps( $caps, $cap, $user_id, $args ) {
	// If the machine user is not defined or the current user is not the machine user, don't filter caps.
	if ( ( ! defined( 'WPCOM_VIP_MACHINE_USER_ID' ) || WPCOM_VIP_MACHINE_USER_ID !== $user_id ) && wpcom_vip_is_two_factor_forced() ) {
		// Use a hard-coded list of caps that give just enough access to set up 2FA
		$subscriber_caps = [
			'read',
			'level_0',
		];

		// You can edit your own user account (required to set up 2FA)
		if ( 'edit_user' === $cap && ! empty( $args ) && (int) $user_id === (int) $args[0] ) {
			$subscriber_caps[] = 'edit_user';
		}

		// WooCommerce caps to check
		$woocommerce_caps = [
			'edit_posts',
			'manage_woocommerce',
			'view_admin_dashboard',
		];

		// Track whether or not we've already granted this user wp-admin access based on WC standards.
		static $user_should_have_wc_admin_access = false;

		// If we haven't granted access yet, and this $cap is a WC cap to check.
		if ( ! $user_should_have_wc_admin_access && in_array( $cap, $woocommerce_caps ) ) {

			// If this user has this $cap and it's `true`, grant this user wp-admin access.
			if ( isset( wp_get_current_user()->allcaps[ $cap ] ) && true === wp_get_current_user()->allcaps[ $cap ] ) {
				$user_should_have_wc_admin_access = true;
				add_filter( 'woocommerce_prevent_admin_access', '__return_false' );
			}
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
					Your account requires <a href="https://docs.wpvip.com/security/two-factor-authentication/">two-factor authentication</a> to be enabled.
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

/**
 * Jetpack Account Protection does not factor in a user's MFA status.
 *
 * If a user has MFA active, we should skip the Account Protection check.
 */
add_filter( 'jetpack_account_protection_user_requires_protection', 'wpcom_vip_two_factor_bypass_jetpack_account_protection', 10, 2 );
function wpcom_vip_two_factor_bypass_jetpack_account_protection( $user_requires_protection, $user ) {
	if ( is_callable( array( 'Two_Factor_Core', 'is_user_using_two_factor' ) ) && Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
		return false;
	}

	return $user_requires_protection;
}
