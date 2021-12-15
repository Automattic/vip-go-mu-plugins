<?php

/**
 * Plugin Name: Jetpack
 * Plugin URI: https://jetpack.com
 * Description: Security, performance, and marketing tools made by WordPress experts. Jetpack keeps your site protected so you can focus on more important things.
 * Author: Automattic
 * Version: 10.4
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
	} else {
		define( 'VIP_JETPACK_DEFAULT_VERSION', '10.4' );
	}
}

// Bump up the batch size to reduce the number of queries run to build a Jetpack sitemap.
if ( ! defined( 'JP_SITEMAP_BATCH_SIZE' ) ) {
	define( 'JP_SITEMAP_BATCH_SIZE', 200 );
}

add_filter( 'jetpack_client_verify_ssl_certs', '__return_true' );

if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || ! constant( 'WPCOM_IS_VIP_ENV' ) ) {
	add_filter( 'jetpack_is_staging_site', '__return_true' );
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

	// Walk through all versions to test, and load the first one that exists
	foreach ( $jetpack_to_test as $version ) {
		if ( 'local' === $version ) {
			$path = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/jetpack/jetpack.php';
		} else {
			$path = WPMU_PLUGIN_DIR . "/jetpack-$version/jetpack.php";
		}

		if ( file_exists( $path ) ) {
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
	if ( version_compare( JETPACK__VERSION, '9.1', '<' ) ) {
		add_filter( 'instagram_cache_oembed_api_response_body', '__return_true' );
	}

	if ( defined( 'VIP_JETPACK_LOADED_VERSION' ) && 'none' !== VIP_JETPACK_LOADED_VERSION ) {
		require_once __DIR__ . '/vip-jetpack/vip-jetpack.php';
	}
}

vip_jetpack_load();
