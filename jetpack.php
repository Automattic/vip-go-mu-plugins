<?php
/**
 * Plugin Name: Jetpack
 * Plugin URI: https://jetpack.com
 * Description: Security, performance, and marketing tools made by WordPress experts. Jetpack keeps your site protected so you can focus on more important things.
 * Author: Automattic
 * Version: 14.0
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
function vip_default_jetpack_version() {
	global $wp_version;

	if ( version_compare( $wp_version, '6.2', '<' ) ) {
		// WordPress 6.1.x
		return '12.5';
	} elseif ( version_compare( $wp_version, '6.3', '<' ) ) {
		// WordPress 6.2.x.
		return '12.8';
	} elseif ( version_compare( $wp_version, '6.4', '<' ) ) { 
		// WordPress 6.3.x
		return '13.1';
	} elseif ( version_compare( $wp_version, '6.5', '<' ) ) {
		// WordPress 6.4.x
		return '13.6';
	} else {
		// WordPress 6.5 and newer.
		return '14.0';
	}
}

// Set the default Jetpack version if it's not already defined
if ( ! defined( 'VIP_JETPACK_DEFAULT_VERSION' ) ) {
	define( 'VIP_JETPACK_DEFAULT_VERSION', vip_default_jetpack_version() );
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

// Default plan object for all VIP sites.
define( 'VIP_JETPACK_DEFAULT_PLAN', array(
	'product_id'         => 'vip',
	'product_slug'       => 'vip',
	'product_name_short' => 'VIP',
	'product_variation'  => 'vip',
	'supports'           => array(
		'videopress',
		'akismet',
		'vaultpress',
		'seo-tools',
		'google-analytics',
		'wordads',
		'search',
	),
	'features'           => array(
		'active'    => array(
			'premium-themes',
			'google-analytics',
			'security-settings',
			'advanced-seo',
			'upload-video-files',
			'video-hosting',
			'send-a-message',
			'whatsapp-button',
			'social-previews',
			'donations',
			'core/audio',
			'republicize',
			'premium-content/container',
			'akismet',
			'vaultpress-backups',
			'vaultpress-backup-archive',
			'vaultpress-storage-space',
			'vaultpress-automated-restores',
			'vaultpress-security-scanning',
			'polldaddy',
			'simple-payments',
			'support',
			'wordads-jetpack',
		),
		'available' => array(
			'security-settings'             => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'advanced-seo'                  => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'upload-video-files'            => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'akismet'                       => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'send-a-message'                => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'whatsapp-button'               => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'social-previews'               => array(
				'jetpack_free',
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'google-analytics'              => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'video-hosting'                 => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'wordads-jetpack'               => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'vaultpress-backups'            => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'vaultpress-backup-archive'     => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'vaultpress-storage-space'      => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'vaultpress-automated-restores' => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'simple-payments'               => array(
				'jetpack_premium',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
			),
			'calendly'                      => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'opentable'                     => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'donations'                     => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'core/video'                    => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'core/cover'                    => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
			),
			'core/audio'                    => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'republicize'                   => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'premium-content/container'     => array(
				'jetpack_premium',
				'jetpack_business',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'support'                       => array(
				'jetpack_premium',
				'jetpack_personal',
				'jetpack_premium_monthly',
				'jetpack_business_monthly',
				'jetpack_personal_monthly',
				'jetpack_security_daily',
				'jetpack_security_daily_monthly',
				'jetpack_security_realtime',
				'jetpack_security_realtime_monthly',
				'jetpack_complete_monthly',
				'jetpack_security_t1_yearly',
				'jetpack_security_t1_monthly',
				'jetpack_security_t2_yearly',
				'jetpack_security_t2_monthly',
			),
			'premium-themes'                => array(
				'jetpack_business',
				'jetpack_business_monthly',
			),
			'vaultpress-security-scanning'  => array(
				'jetpack_business',
				'jetpack_business_monthly',
			),
			'polldaddy'                     => array(
				'jetpack_business',
				'jetpack_business_monthly',
			),
		),
	),
) );

/**
 * Prevent the jetpack_active_plan from ever being overridden.
 *
 * All VIP sites should always have have a valid Jetpack plan.
 *
 * This will prevent issues from the plan option being corrupted,
 * which can then break features like Jetpack Search.
 */
add_filter( 'pre_option_jetpack_active_plan', function ( $pre_option ) {
	if ( true === WPCOM_IS_VIP_ENV && defined( 'VIP_JETPACK_DEFAULT_PLAN' ) ) {
		return VIP_JETPACK_DEFAULT_PLAN;
	}

	return $pre_option;
} );


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
			$path = WPVIP_MU_PLUGIN_DIR . '/jetpack/jetpack.php';
		} else {
			$path = WPVIP_MU_PLUGIN_DIR . "/jetpack-$version/jetpack.php";
		}

		if ( file_exists( $path ) ) {
			// In a rare edge case, the plugin could be present in `active_plugins` option,
			// That would lead to Jetpack Autoloader Guard trying to load autoloaders for `jetpack` and `jetpack-$version`
			// This in turn would lead to a fatal error, when jetpack and jetpack-$version are the same version.
			add_filter( 'option_active_plugins', function ( $option ) {
				if ( ! is_array( $option ) ) {
					return $option;
				}

				foreach ( $option as $i => $plugin ) {
					if ( str_ends_with( $plugin, '/jetpack.php' ) ) {
						unset( $option[ $i ] );
						break;
					}
				}
				return $option;
			} );

			if ( is_multisite() ) {
				// The same edge case as above, but for when Jetpack is network activated.
				add_filter( 'site_option_active_sitewide_plugins', function ( $option ) {
					if ( ! is_array( $option ) ) {
						return $option;
					}

					foreach ( $option as $plugin => $i ) {
						if ( str_ends_with( $plugin, '/jetpack.php' ) ) {
							unset( $option[ $plugin ] );
							break;
						}
					}
					return $option;
				} );
			}

			require_once $path;
			if ( class_exists( 'Jetpack' ) ) {
				define( 'VIP_JETPACK_LOADED_VERSION', $version );
			} else {
				trigger_error( 'Jetpack could not be loaded and initialized due to a bootstrapping issue.', E_USER_WARNING );
			}

			// We should break even if we failed to load Jetpack, because some constants like JETPACK_VERSION were probably already set
			break;

			// Trigger a E_USER_WARNING in non-production environments if the pinned version could not be loaded.
		} elseif ( ! file_exists( $path ) && defined( 'VIP_JETPACK_PINNED_VERSION' ) && wp_in( constant( 'VIP_JETPACK_PINNED_VERSION' ), $path ) ) {
			if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'production' !== constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				trigger_error( 'Jetpack loading error: ' . constant( 'VIP_JETPACK_PINNED_VERSION' ) . ' could not be loaded, loading ' . constant( 'VIP_JETPACK_DEFAULT_VERSION' ) . ' instead.', E_USER_WARNING );
			}
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
