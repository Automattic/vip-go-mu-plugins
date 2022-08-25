<?php
/*
 * Plugin Name: VIP Parse.ly Integration
 * Plugin URI: https://parse.ly
 * Description: Content analytics made easy. Parse.ly gives creators, marketers and developers the tools to understand content performance, prove content value, and deliver tailored content experiences that drive meaningful results.
 * Author: Automattic
 * Version: 1.0
 * Author URI: https://wpvip.com/
 * License: GPL2+
 * Text Domain: wp-parsely
 * Domain Path: /languages/
 */

namespace Automattic\VIP\WP_Parsely_Integration;

// The default version is the first entry in the SUPPORTED_VERSIONS list.
const SUPPORTED_VERSIONS = [
	'3.3',
	'3.5',
	'3.2',
	'3.1',
];

/**
 * Class to hold Parse.ly loading info.
 * Will prevent this information from being queried multiple times
 * by keeping the status in a static variable.
 */
final class Parsely_Loader_Info {
	/**
	 * Strings for Parse.ly integration types.
	 */
	const INTEGRATION_TYPE_MUPLUGINS        = 'MUPLUGINS';
	const INTEGRATION_TYPE_MUPLUGINS_SILENT = 'MUPLUGINS_SILENT';
	const INTEGRATION_TYPE_SELF_MANAGED     = 'SELF_MANAGED';
	const INTEGRATION_TYPE_NONE             = 'NONE';

	/**
	 * String for when no wp-parsely version can be detected.
	 */
	const VERSION_NONE = 'NONE';

	/**
	 * Strings for Parse.ly service types.
	 */
	const SERVICE_TYPE_PAID = 'PAID';
	const SERVICE_TYPE_NONE = 'NONE';

	/**
	 * @var boolean
	 */
	private static $active;

	/**
	 * @var string
	 */
	private static $integration_type;

	/**
	 * @var array The Parse.ly WordPress options dictionary.
	 */
	private static $parsely_options;

	/**
	 * @var string
	 */
	private static $service_type;

	/**
	 * @var string
	 */
	private static $version;

	/**
	 * Fetches the active status.
	 * @return boolean
	 */
	public static function get_active() {
		if ( null === self::$active ) {
			$integration_type = self::get_integration_type();

			if ( self::INTEGRATION_TYPE_MUPLUGINS === $integration_type ||
				self::INTEGRATION_TYPE_MUPLUGINS_SILENT === $integration_type ||
				self::INTEGRATION_TYPE_SELF_MANAGED === $integration_type
			) {
				self::set_active( true );
			} else {
				self::set_active( false );
			}
		}

		return self::$active;
	}

	/**
	 * Sets the private $active property.
	 * @param boolean $active
	 */
	public static function set_active( $active ) {
		self::$active = $active;
	}

	/**
	 * Fetches the integration type.
	 * @return string
	 */
	public static function get_integration_type() {
		if ( null === self::$integration_type ) {
			self::set_integration_type( self::INTEGRATION_TYPE_NONE );
		}

		return self::$integration_type;
	}

	/**
	 * Sets the private $integration_type property.
	 * @param string $integration_type
	 */
	public static function set_integration_type( $integration_type ) {
		self::$integration_type = $integration_type;
	}

	/**
	 * Fetches Parse.ly options dictionary.
	 * @return array
	 */
	public static function get_parsely_options() {
		if ( null === self::$parsely_options ) {
			self::set_parsely_options( get_option( 'parsely', [] ) );
		}

		return self::$parsely_options;
	}

	/**
	 * Sets the private $parsely_options property.
	 * @param array $parsely_options
	 */
	public static function set_parsely_options( $parsely_options ) {
		self::$parsely_options = $parsely_options;
	}

	/**
	 * Fetches the service type.
	 * @return string
	 */
	public static function get_service_type() {
		if ( null === self::$service_type ) {
			$integration_type = self::get_integration_type();

			if ( self::INTEGRATION_TYPE_MUPLUGINS === $integration_type ||
				self::INTEGRATION_TYPE_SELF_MANAGED === $integration_type
			) {
				self::set_service_type( self::SERVICE_TYPE_PAID );
			}

			self::set_service_type( self::SERVICE_TYPE_NONE );
		}

		return self::$service_type;
	}

	/**
	 * Sets the private $service_type property.
	 * @param string $service_type
	 */
	public static function set_service_type( $service_type ) {
		self::$service_type = $service_type;
	}

	/**
	 * Fetches the version.
	 * @return string
	 */
	public static function get_version() {
		if ( null === self::$version ) {
			$parsely_options = self::get_parsely_options();

			if ( array_key_exists( 'plugin_version', $parsely_options ) ) {
				self::set_version( $parsely_options['plugin_version'] );
			} else {
				self::set_version( self::VERSION_NONE );
			}
		}

		return self::$version;
	}

	/**
	 * Sets the private $version property.
	 */
	public static function set_version( $version ) {
		self::$version = $version;
	}
}

/**
 * Annotate the `parsely` option with `'meta_type' => 'repeated_metas'`.
 * When this filter is applied thusly, this prints parsely meta as multiple `<meta />` tags
 * vs. a single structured ld+json schema.
 * This is desirable since many of our sites already have curated schema setups & this could interfere.
 *
 * @param mixed $parsely_options The value of the `parsely` option from the database. This materializes as an array (but is false when not yet set).
 * @return array The annotated array.
 */
function alter_option_use_repeated_metas( $parsely_options = [] ) {
	$parsely_options['meta_type'] = 'repeated_metas';
	return $parsely_options;
}

function maybe_load_plugin() {
	/**
	 * Sourcing the wp-parsely plugin via mu-plugins is generally opt-in.
	 * To enable it on your site, add this line:
	 *
	 * add_filter( 'wpvip_parsely_load_mu', '__return_true' );
	 *
	 * We enable it for some sites via the `_wpvip_parsely_mu` blog option.
	 * To prevent it from loading even when this condition is met, add this line:
	 *
	 * add_filter( 'wpvip_parsely_load_mu', '__return_false' );
	 */
	if ( ! apply_filters( 'wpvip_parsely_load_mu', get_option( '_wpvip_parsely_mu' ) === '1' ) ) {
		// Register Parse.ly as a mu-plugins integration type in loader info.
		Parsely_Loader_Info::set_integration_type( Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS );
		return;
	}

	// Don't load if wp-parsely has already been loaded, for example when the
	// user has a self-managed wp-parsely installation.
	if ( class_exists( 'Parsely' ) || class_exists( 'Parsely\Parsely' ) ) {
		// Register Parse.ly as a self-managed integration type in loader info.
		Parsely_Loader_Info::set_integration_type( Parsely_Loader_Info::INTEGRATION_TYPE_SELF_MANAGED );
		return;
	}

	// Enqueuing the disabling of Parse.ly features when the plugin is loaded (after the `plugins_loaded` hook)
	// We need priority 0, so it's executed before `widgets_init`
	add_action( 'init', __NAMESPACE__ . '\maybe_disable_some_features', 0 );

	$versions_to_try = SUPPORTED_VERSIONS;

	/**
	 * Allows specifying a major version of the plugin per-site.
	 * If the version is invalid, the default version will be used.
	 */
	$specified_version = apply_filters( 'wpvip_parsely_version', false );

	if ( $specified_version ) {
		if ( in_array( $specified_version, SUPPORTED_VERSIONS ) ) {
			array_unshift( $versions_to_try, $specified_version );
			$versions_to_try = array_unique( $versions_to_try );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error(
				sprintf( 'Invalid value configured via wpvip_parsely_version filter: %s', esc_html( $specified_version ) ),
				E_USER_WARNING
			);
		}
	}

	foreach ( $versions_to_try as $version ) {
		$entry_file = __DIR__ . '/wp-parsely-' . $version . '/wp-parsely.php';
		if ( ! is_readable( $entry_file ) ) {
			continue;
		}

		// Requiring actual Parse.ly plugin
		require_once $entry_file;

		// Requiring VIP's customizations over Parse.ly
		$vip_parsely_plugin = __DIR__ . '/vip-parsely/vip-parsely.php';
		if ( is_readable( $vip_parsely_plugin ) ) {
			// Register Parse.ly as a mu-plugins integration type in loader info.
			Parsely_Loader_Info::set_integration_type( Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS );
			Parsely_Loader_Info::set_version( $version );

			require_once $vip_parsely_plugin;
		}

		return;
	}
}
add_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_plugin' );

function maybe_disable_some_features() {
	if ( isset( $GLOBALS['parsely'] ) && is_a( $GLOBALS['parsely'], 'Parsely\Parsely' ) ) {
		// If the plugin was loaded solely by the option, hide the UI
		if ( apply_filters( 'wpvip_parsely_hide_ui_for_mu', ! has_filter( 'wpvip_parsely_load_mu' ) ) ) {
			// Register Parse.ly as a silent integration type in loader info.
			Parsely_Loader_Info::set_integration_type( Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS_SILENT );

			remove_action( 'init', 'Parsely\parsely_wp_admin_early_register' );
			remove_action( 'init', 'Parsely\init_recommendations_block' );
			remove_action( 'enqueue_block_editor_assets', 'Parsely\init_content_helper' );
			remove_action( 'admin_init', 'Parsely\parsely_admin_init_register' );
			remove_action( 'widgets_init', 'Parsely\parsely_recommended_widget_register' );

			// Don't show the row action links.
			add_filter( 'wp_parsely_enable_row_action_links', '__return_false' );
			add_filter( 'wp_parsely_enable_rest_api_support', '__return_false' );
			add_filter( 'wp_parsely_enable_related_api_proxy', '__return_false' );

			// Default to "repeated metas".
			add_filter( 'option_parsely', __NAMESPACE__ . '\alter_option_use_repeated_metas' );

			// Remove the Parse.ly Recommended Widget.
			unregister_widget( 'Parsely_Recommended_Widget' );
		}
	}
}
