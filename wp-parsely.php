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
	'3.1',
	'3.0',
	'2.6',
];

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
		return;
	}

	// Bail if the plugin has already initialized elsewhere
	if ( class_exists( 'Parsely' ) || class_exists( 'Parsely\Parsely' ) ) {
		return;
	}

	// Enqueuing the disabling of Parse.ly features when the plugin is loaded (after the `plugins_loaded` hook)
	// We need priority 0, so it's executed before `widgets_init`
	add_action( 'init', __NAMESPACE__ . '\maybe_disable_some_features', 0 );

	/**
	 * Hot fix: Password protected posts prior to 3.0.1 potentially leak post info via metadata.
	 * This can be removed once we no longer source an earlier version.
	 */
	add_filter( 'wp_parsely_metadata', __NAMESPACE__ . '\clear_protected_metadata_prior_to_3_0_1', 9, 2 );

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
			require_once $vip_parsely_plugin;
		}

		return;
	}
}
add_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_plugin' );

function maybe_disable_some_features() {
	if ( isset( $GLOBALS['parsely'] ) && ( is_a( $GLOBALS['parsely'], 'Parsely' ) || is_a( $GLOBALS['parsely'], 'Parsely\Parsely' ) ) ) {
		$is_less_than_3 = version_compare( $GLOBALS['parsely']::VERSION, '3.0.0', '<' );

		// If the plugin was loaded solely by the option, hide the UI
		if ( apply_filters( 'wpvip_parsely_hide_ui_for_mu', ! has_filter( 'wpvip_parsely_load_mu' ) ) ) {
			if ( $is_less_than_3 ) {
				remove_action( 'admin_menu', array( $GLOBALS['parsely'], 'add_settings_sub_menu' ) );
				remove_action( 'admin_footer', array( $GLOBALS['parsely'], 'display_admin_warning' ) );
				remove_action( 'widgets_init', 'parsely_recommended_widget_register' );
			} else {
				remove_action( '_admin_menu', 'Parsely\parsely_admin_menu_register' );
				remove_action( 'admin_init', 'Parsely\parsely_admin_init_register' );
				remove_action( 'widgets_init', 'Parsely\parsely_recommended_widget_register' );

				// If we're disabled, we want to make sure we don't show the row action links.
				add_filter( 'wp_parsely_enable_row_action_links', '__return_false' );
			}

			// ..& default to "repeated metas"
			add_filter( 'option_parsely', __NAMESPACE__ . '\alter_option_use_repeated_metas' );

			// Remove the Parse.ly Recommended Widget
			unregister_widget( 'Parsely_Recommended_Widget' );
		} elseif ( $is_less_than_3 ) {
			// If we have the UI, we want to load "Open on Parsely links". Only required on <3.0, now it's enabled by default.
			add_filter( 'wp_parsely_enable_row_action_links', '__return_true' );
		}
	}
}

/*
 * Password protected posts prior to 3.0.1 potentially leak post info via metadata.
 * This is a hot fix that clears the metadata when appropriate, otherwise, it returns the input value.
 * @see https://github.com/Parsely/wp-parsely/blob/3.0.1/src/class-parsely.php#L280-L294
 * @see https://github.com/Parsely/wp-parsely/pull/547
 * @return array By default, return the input value. Return an empty array when:
 *   - the wp-parsely plugin version is less than 3.0.1
 *   - the password check is not explicitly bypassed via `wp_parsely_skip_post_password_check` filter
 *   - a password is required, but not provided
 */
function clear_protected_metadata_prior_to_3_0_1( $parsely_page, $post ) {
	if (
		version_compare( $GLOBALS['parsely']::VERSION, '3.0.1', '<' ) &&
		! apply_filters( 'wp_parsely_skip_post_password_check', false, $post ) &&
		post_password_required( $post )
	) {
		return [];
	}
	return $parsely_page;
}
