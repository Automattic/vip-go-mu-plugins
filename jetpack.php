<?php

/**
 * Plugin Name: Jetpack
 * Plugin URI: https://jetpack.com
 * Description: Security, performance, and marketing tools made by WordPress experts. Jetpack keeps your site protected so you can focus on more important things.
 * Author: Automattic
 * Version: 11.2
 * Author URI: https://jetpack.com
 * License: GPL2+
 * Text Domain: jetpack
 * Requires at least: 5.7
 * Requires PHP: 5.6
 *
 * @package automattic/jetpack
 */

// Choose an appropriate default Jetpack version, ensuring that older WordPress versions
// are not using a too modern Jetpack version that is not compatible with it
if ( ! defined( 'VIP_JETPACK_DEFAULT_VERSION' ) ) {
	if ( version_compare( $wp_version, '5.6', '<' ) ) {
		define( 'VIP_JETPACK_DEFAULT_VERSION', '9.4' );
	} elseif ( version_compare( $wp_version, '5.7', '<' ) ) {
		define( 'VIP_JETPACK_DEFAULT_VERSION', '9.8' );
	} elseif ( version_compare( $wp_version, '5.8', '<' ) ) {
		define( 'VIP_JETPACK_DEFAULT_VERSION', '10.4' );
	} elseif ( version_compare( $wp_version, '5.9', '<' ) ) {
		define( 'VIP_JETPACK_DEFAULT_VERSION', '10.9' );
	} else {
		define( 'VIP_JETPACK_DEFAULT_VERSION', '11.2' );
	}
}

// Bump up the batch size to reduce the number of queries run to build a Jetpack sitemap.
if ( ! defined( 'JP_SITEMAP_BATCH_SIZE' ) ) {
	define( 'JP_SITEMAP_BATCH_SIZE', 200 );
}

add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

/**
 * Decide whether we need to enable JP staging mode, or prevent it.
 *
 * Mainly used to help prevent a site from accidentally taking over the connection
 * of another site. Happens if the database is copied over and the "auth" keys are then shared.
 *
 * @see https://jetpack.com/support/staging-sites/
 */
function vip_toggle_jetpack_staging_mode() {
	$is_vip_site = defined( 'WPCOM_IS_VIP_ENV' ) && constant( 'WPCOM_IS_VIP_ENV' );

	if ( ! $is_vip_site ) {
		// Default non-VIP sites to staging mode (likely local dev or custom staging).
		add_filter( 'jetpack_is_staging_site', '__return_true' );
		return;
	}

	$is_maintenance_mode = defined( 'WPCOM_VIP_SITE_MAINTENANCE_MODE' ) && WPCOM_VIP_SITE_MAINTENANCE_MODE;
	$is_production_site  = defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' === VIP_GO_APP_ENVIRONMENT;
	if ( $is_maintenance_mode && ! $is_production_site ) {
		// Specifically targetting data syncs here, we want to prevent Jetpack from contacting the site and potentially causing an identity crisis.
		add_filter( 'jetpack_is_staging_site', '__return_true' );
		return;
	}

	// By default, JP will set all non-production sites to staging. But on VIP, the preprod/develop sites should still have their own real connections.
	// So we'll ensure here it's not set to staging mode since it will break SSO and prevent data from being passed to WPcom.
	add_filter( 'jetpack_is_staging_site', '__return_false' );
}

/**
 * Add JP broken connection debug headers
 *
 * NOTE - this _must_ come _before_ jetpack/jetpack.php is loaded, b/c the signature verification is
 * performed in __construct() of the Jetpack class, so hooking after it has been loaded is too late
 *
 * $error is a WP_Error (always) and contains a "signature_details" data property with this structure:
 * The error_code has one of the following values:
 * - malformed_token
 * - malformed_user_id
 * - unknown_token
 * - could_not_sign
 * - invalid_nonce
 * - signature_mismatch
 */
function vip_jetpack_token_send_signature_error_headers( $error ) {
	if ( ! vip_is_jetpack_request() || headers_sent() || ! is_wp_error( $error ) ) {
		return;
	}

	$error_data = $error->get_error_data();

	if ( ! isset( $error_data['signature_details'] ) ) {
		return;
	}

	header( sprintf(
		'X-Jetpack-Signature-Error: %s',
		$error->get_error_code()
	) );

	header( sprintf(
		'X-Jetpack-Signature-Error-Message: %s',
		$error->get_error_message()
	) );

	header( sprintf(
		'X-Jetpack-Signature-Error-Details: %s',
		base64_encode( wp_json_encode( $error_data['signature_details'] ) )
	) );
}

add_action( 'jetpack_verify_signature_error', 'vip_jetpack_token_send_signature_error_headers' );

/**
 * Load the jetpack plugin according to several defines:
 * - If VIP_JETPACK_SKIP_LOAD is true, Jetpack will not be loaded
 * - If WPCOM_VIP_JETPACK_LOCAL is true, Jetpack will be loaded from client-mu-plugins
 * - If VIP_JETPACK_PINNED_VERSION is defined, it will try to load this specific version
 * - Finally, it will try to load VIP_JETPACK_DEFAULT_VERSION as the fallback
 */
function vip_jetpack_load() {
	if ( defined( 'VIP_JETPACK_LOADED_VERSION' ) ) {
		return;
	}

	if ( defined( 'VIP_JETPACK_SKIP_LOAD' ) && VIP_JETPACK_SKIP_LOAD ) {
		define( 'VIP_JETPACK_LOADED_VERSION', 'none' );
		return;
	}

	$jetpack_to_test = array();

	if ( defined( 'WPCOM_VIP_JETPACK_LOCAL' ) && WPCOM_VIP_JETPACK_LOCAL ) {
		$jetpack_to_test[] = 'local';
	}

	if ( defined( 'VIP_JETPACK_PINNED_VERSION' ) ) {
		$jetpack_to_test[] = VIP_JETPACK_PINNED_VERSION;
	}

	$jetpack_to_test[] = VIP_JETPACK_DEFAULT_VERSION;

	// Because the versioned jetpack folders will live outside this repository, we want
	// to have a backup to unversioned "jetpack" folder
	$jetpack_to_test[] = '';

	// Walk through all versions to test, and load the first one that exists
	foreach ( $jetpack_to_test as $version ) {
		if ( 'local' === $version ) {
			$path = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/jetpack/jetpack.php';
		} elseif ( '' === $version ) {
			$path = WPMU_PLUGIN_DIR . '/jetpack/jetpack.php';
		} else {
			$path = WPMU_PLUGIN_DIR . "/jetpack-$version/jetpack.php";
		}

		if ( file_exists( $path ) ) {
			// In a rare edge case, the plugin could be present in `active_plugins` option,
			// That would lead to Jetpack Autoloader Guard trying to load autoloaders for `jetpack` and `jetpack-$version`
			// This in turn would lead to a fatal error, when jetpack and jetpack-$version are the same version.
			add_filter( 'option_active_plugins', function( $option ) {
				if ( ! is_array( $option ) ) {
					return $option;
				}

				foreach ( $option as $i => $plugin ) {
					if ( wp_endswith( $plugin, '/jetpack.php' ) ) {
						unset( $option[ $i ] );
						break;
					}
				}
				return $option;
			} );

		    	// The same edge case as above, but for when Jetpack is network activated.
			add_filter( 'site_option_active_sitewide_plugins', function( $option ) {
				if ( ! is_array( $option ) ) {
					return $option;
				}

				foreach ( $option as $plugin => $i ) {
					if ( wp_endswith( $plugin, '/jetpack.php' ) ) {
						unset( $option[ $plugin ] );
						break;
					}
				}
				return $option;
			} );
			
			require_once $path;
			define( 'VIP_JETPACK_LOADED_VERSION', $version );
			break;
		}
	}

	/**
	 * Enables object caching for the response sent by Instagram when querying for Instagram image HTML.
	 *
	 * This cannot be included inside Jetpack because it ships with caching disabled by default.
	 * By enabling caching it's possible to save time in uncached page renders.
	 *
	 * We need Jetpack to be loaded as this has been deprecated in version 9.1, and if the filter is
	 * added in that version or newer, a warning is shown on every WordPress request
	 */
	if ( defined( 'JETPACK__VERSION' ) && version_compare( JETPACK__VERSION, '9.1', '<' ) ) {
		add_filter( 'instagram_cache_oembed_api_response_body', '__return_true' );
	}

	if ( defined( 'VIP_JETPACK_LOADED_VERSION' ) && 'none' !== VIP_JETPACK_LOADED_VERSION ) {
		// Configure the staging mode flag before we load the plugin, preventing any load order issues.
		vip_toggle_jetpack_staging_mode();

		require_once __DIR__ . '/vip-jetpack/vip-jetpack.php';
	}
}

if ( ! defined( 'WP_INSTALLING' ) ) {
	vip_jetpack_load();
}
