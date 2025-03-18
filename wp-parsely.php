<?php
/**
 * Plugin Name: VIP Parse.ly Integration
 * Plugin URI: https://parse.ly
 * Description: Content analytics made easy. Parse.ly gives creators, marketers and developers the tools to understand content performance, prove content value, and deliver tailored content experiences that drive meaningful results.
 * Author: Automattic
 * Version: 1.0
 * Author URI: https://wpvip.com/
 * License: GPL2+
 * Text Domain: wp-parsely
 * Domain Path: /languages/
 *
 * @package Automattic\VIP\WP_Parsely_Integration
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

namespace Automattic\VIP\WP_Parsely_Integration;

use Parsely\Parsely;

/**
 * The default version is the first entry in the SUPPORTED_VERSIONS list.
 */
const SUPPORTED_VERSIONS = [
	'3.17',
	'3.16',
	'3.15',
];

/**
 * Keep track of Parse.ly loading info in one place.
 */
final class Parsely_Loader_Info {
	// Defaults for when detection was not possible.
	const VERSION_UNKNOWN = 'UNKNOWN';

	/**
	 * Status of the plugin.
	 *
	 * @var bool
	 */
	private static bool $active;

	/**
	 * Integration type of the plugin.
	 *
	 * @var string
	 */
	private static string $integration_type;

	/**
	 * Version of the plugin.
	 *
	 * @var string
	 */
	private static string $version;

	/**
	 * Check if the plugin is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return isset( self::$active ) ? self::$active : false;
	}

	/**
	 * Set active.
	 *
	 * @param bool $active Status of the plugin.
	 */
	public static function set_active( bool $active ): void {
		self::$active = $active;
	}

	/**
	 * Get integration type.
	 *
	 * @return string
	 */
	public static function get_integration_type(): string {
		return isset( self::$integration_type ) ? self::$integration_type : Parsely_Integration_Type::NONE;
	}

	/**
	 * Set integration type.
	 *
	 * @param string $integration_type Type of the integration.
	 */
	public static function set_integration_type( string $integration_type ): void {
		self::$integration_type = $integration_type;
	}

	/**
	 * Get version.
	 *
	 * @return string
	 */
	public static function get_version(): string {
		return isset( self::$version ) ? self::$version : self::VERSION_UNKNOWN;
	}

	/**
	 * Set version.
	 *
	 * @param string $version Version of the plugin.
	 */
	public static function set_version( string $version ): void {
		self::$version = $version;
	}

	/**
	 * Returns configuration which represents how the plugin is configured in site.
	 *
	 * @return array{
	 *   is_pinned_version: bool,
	 *   site_id: string,
	 *   have_api_secret: bool,
	 *   is_javascript_disabled: bool,
	 *   is_autotracking_disabled: bool,
	 *   should_track_logged_in_users: bool,
	 *   tracked_post_types: array{
	 *     name: string,
	 *     track_type: string,
	 *   }[]
	 * }
	 */
	public static function get_configs() {
		if ( ! self::is_active() ) {
			return null;
		}

		$configs = array();
		$options = self::get_parsely_options();

		$configs['is_pinned_version']            = has_filter( 'wpvip_parsely_version' );
		$configs['site_id']                      = $options['apikey'] ?? '';
		$configs['have_api_secret']              = '' !== ( $options['api_secret'] ?? '' );
		$configs['is_javascript_disabled']       = (bool) ( $options['disable_javascript'] ?? false );
		$configs['is_autotracking_disabled']     = (bool) ( $options['disable_autotrack'] ?? false );
		$configs['should_track_logged_in_users'] = (bool) ( $options['track_authenticated_users'] ?? false );

		$configs['tracked_post_types'] = array();
		$post_types                    = get_post_types( array( 'public' => true ) );
		$tracked_post_types            = $options['track_post_types'] ?? array();
		$tracked_page_types            = $options['track_page_types'] ?? array();
		foreach ( $post_types as $post_type ) {
			$tracked_post_type         = array();
			$tracked_post_type['name'] = $post_type;

			if ( in_array( $post_type, $tracked_post_types ) ) {
				$tracked_post_type['track_type'] = 'post';
			} elseif ( in_array( $post_type, $tracked_page_types ) ) {
				$tracked_post_type['track_type'] = 'non-post';
			} else {
				$tracked_post_type['track_type'] = 'do-not-track';
			}

			array_push( $configs['tracked_post_types'], $tracked_post_type );
		}

		return $configs;
	}

	/**
	 * Get Parse.ly options.
	 */
	public static function get_parsely_options(): array {
		if ( ! self::is_active() ) {
			return array();
		}

		/**
		 * Parse.ly options, plugin may be not loaded at this moment in the runtime, but we want to check the options anyway.
		 *
		 * @var array
		 */
		if ( isset( $GLOBALS['parsely'] ) && is_a( $GLOBALS['parsely'], 'Parsely\Parsely' ) ) {
			$parsely_options = $GLOBALS['parsely']->get_options();
		} else {
			$parsely_options = get_option( 'parsely', [] );
		}

		return $parsely_options;
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

/**
 * Detects if the user is attempting to activate wp-parsely in wp-admin.
 * The Parse.ly plugin will not be in active_plugins, but is pending activation.
 */
function is_queued_for_activation() {
	if ( ! is_admin() ) {
		return false;
	}

	// phpcs:disable
	if ( isset( $_GET['action'] ) && isset( $_GET['plugin'] ) ) {
		if ( 'activate' === $_GET['action'] && false !== strpos( wp_unslash( $_GET['plugin'] ), 'wp-parsely.php' ) ) {
			return true;
		}
	}

	// Bulk activation will activate via form submission
	$isBulkActivation1 = isset( $_POST['action'] ) && 'activate-selected' === wp_unslash( $_POST['action'] );
	$isBulkActivation2 = isset( $_POST['action2'] ) && 'activate-selected' === wp_unslash( $_POST['action2'] );
	if ( ( $isBulkActivation1 || $isBulkActivation2 ) && isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) ) {
		$plugins_being_activated = wp_unslash( $_POST['checked'] );

		foreach ( $plugins_being_activated as $plugin ) {
			if ( false !== strpos( $plugin, 'wp-parsely.php' ) ) {
				return true;
			}
		}
	}
	// phpcs:enable

	return false;
}

/**
 * Sourcing the wp-parsely plugin via mu-plugins is generally opt-in.
 * To enable it on your site, add this line:
 * add_filter( 'wpvip_parsely_load_mu', '__return_true' );
 *
 * To prevent it from loading even when this condition is met, add this line:
 * add_filter( 'wpvip_parsely_load_mu', '__return_false' );
 */
function maybe_load_plugin() {
	// If the user is activating the plugin in this request, do not try to load wp-parsely via mu.
	if ( is_queued_for_activation() ) {
		return;
	}

	$filtered_load_status = apply_filters( 'wpvip_parsely_load_mu', null );

	switch ( true ) {
		// Self-managed
		case class_exists( 'Parsely' ) || class_exists( 'Parsely\Parsely' ):
			Parsely_Loader_Info::set_active( true );
			Parsely_Loader_Info::set_integration_type( Parsely_Integration_Type::SELF_MANAGED );
			$parsely_options = Parsely_Loader_Info::get_parsely_options();
			if ( array_key_exists( 'plugin_version', $parsely_options ) ) {
				Parsely_Loader_Info::set_version( $parsely_options['plugin_version'] );
			}
			break;
		// Integrations-managed
		case defined( 'VIP_PARSELY_ENABLED' ):
			Parsely_Loader_Info::set_active( true === constant( 'VIP_PARSELY_ENABLED' ) );
			Parsely_Loader_Info::set_integration_type(
				Parsely_Loader_Info::is_active()
					? Parsely_Integration_Type::ENABLED_CONSTANT
					: Parsely_Integration_Type::DISABLED_CONSTANT
			);
			break;
		// Filter-managed - enabled
		case $filtered_load_status:
			Parsely_Loader_Info::set_active( true );
			Parsely_Loader_Info::set_integration_type( Parsely_Integration_Type::ENABLED_MUPLUGINS_FILTER );
			break;
		// Filter-managed - disabled
		case false === $filtered_load_status:
			Parsely_Loader_Info::set_active( false );
			Parsely_Loader_Info::set_integration_type( Parsely_Integration_Type::DISABLED_MUPLUGINS_FILTER );
			break;
		// Not configured in any way
		default:
			Parsely_Loader_Info::set_active( false );
			Parsely_Loader_Info::set_integration_type( Parsely_Integration_Type::NONE );
			break;
	}

	if ( ! Parsely_Loader_Info::is_active() || Parsely_Integration_Type::SELF_MANAGED === Parsely_Loader_Info::get_integration_type() ) {
		return;
	}

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

	$versions_exist = false;
	foreach ( $versions_to_try as $version ) {
		$entry_file = __DIR__ . '/wp-parsely-' . $version . '/wp-parsely.php';
		if ( is_readable( $entry_file ) ) {
			$versions_exist = true;
			break;
		}
	}

	if ( ! $versions_exist ) {
		// Attempt to load the submodule.
		$entry_file = __DIR__ . '/wp-parsely/wp-parsely.php';
	}

	// Require the actual wp-parsely plugin.
	if ( ! is_readable( $entry_file ) ) {
		Parsely_Loader_Info::set_active( false );
		return;
	}

	require_once $entry_file;

	if ( defined( '\Parsely\PARSELY_VERSION' ) ) {
		Parsely_Loader_Info::set_version( constant( '\Parsely\PARSELY_VERSION' ) );
	}

	// Require VIP's customizations over wp-parsely.
	$vip_parsely_plugin = __DIR__ . '/vip-parsely/vip-parsely.php';
	if ( is_readable( $vip_parsely_plugin ) ) {
		require_once $vip_parsely_plugin;
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\maybe_load_plugin', 1 );

/**
 * Enum which represent all options to integrate `wp-parsely`.
 */
abstract class Parsely_Integration_Type { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	// When Parse.ly is active.
	const ENABLED_MUPLUGINS_FILTER = 'ENABLED_MUPLUGINS_FILTER';
	const ENABLED_CONSTANT         = 'ENABLED_CONSTANT';
	const SELF_MANAGED             = 'SELF_MANAGED';
	// When Parse.ly is not active.
	const DISABLED_MUPLUGINS_FILTER = 'DISABLED_MUPLUGINS_FILTER';
	const DISABLED_CONSTANT         = 'DISABLED_CONSTANT';          // Prevent loading of plugin based on integration meta attribute or customers can also define it.
	// When Parse.ly is not configured in any way.
	const NONE = 'NONE';
}
