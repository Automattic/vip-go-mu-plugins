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
 */

declare(strict_types=1);

use Automattic\VIP\Parsely\Telemetry\Telemetry;
use Automattic\VIP\Parsely\Telemetry\Tracks;
use Automattic\VIP\Support_User\User as Support_User;

/**
 * This is determined by our value passed to the `WP_Widget` constructor.
 *
 * @see https://github.com/Parsely/wp-parsely/blob/e9f1b8cd1a94743e068681a8106176d23857992d/src/class-parsely-recommended-widget.php#L28
 */
const WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID = 'parsely_recommended_widget';

// Telemetry is enabled by default on non-production sites.
add_filter( 'wp_parsely_enable_wpadmin_telemetry', '__return_true' );
if ( apply_filters( 'wp_parsely_enable_telemetry_backend', true ) ) {
	require __DIR__ . '/Telemetry/class-telemetry.php';
	require __DIR__ . '/Telemetry/class-telemetry-system.php';
	require __DIR__ . '/Telemetry/Tracks/class-tracks.php';
	require __DIR__ . '/Telemetry/Tracks/class-tracks-event.php';
	add_action(
		'init',
		function (): void {
			$tracks    = new Tracks();
			$telemetry = new Telemetry( $tracks );

			require_once __DIR__ . '/Telemetry/Events/track-settings-page-loaded.php';
			$telemetry->register_event(
				array(
					'action_hook' => 'load-settings_page_parsely',
					'callable'    => 'Automattic\VIP\Parsely\Telemetry\track_settings_page_loaded',
				)
			);

			require_once __DIR__ . '/Telemetry/Events/track-option-updated.php';
			$telemetry->register_event(
				array(
					'action_hook'   => 'update_option_parsely',
					'callable'      => 'Automattic\VIP\Parsely\Telemetry\track_option_updated',
					'accepted_args' => 2,
				)
			);

			require_once __DIR__ . '/Telemetry/Events/track-delete-widget.php';
			$telemetry->register_event(
				array(
					'action_hook'   => 'delete_widget',
					'callable'      => 'Automattic\VIP\Parsely\Telemetry\track_delete_widget',
					'accepted_args' => 3,
				)
			);

			require_once __DIR__ . '/Telemetry/Events/track-widget-updated.php';
			$telemetry->register_event(
				array(
					'action_hook'   => 'widget_update_callback',
					'callable'      => 'Automattic\VIP\Parsely\Telemetry\track_widget_updated',
					'accepted_args' => 4,
				)
			);

			$telemetry->run();
		}
	);
}

/**
 * Allows VIP Support users to use the Parse.ly Content Helper feature.
 *
 * @param bool    $current_user_can_use_pch_feature Whether the current user can use the Parse.ly Content Helper feature.
 * @param string  $feature_name The name of the feature.
 * @param WP_User $current_user The current user.
 *
 * @return bool Whether the current user can use the Parse.ly Content Helper feature.
 */
add_filter( 'wp_parsely_current_user_can_use_pch_feature', function ( $current_user_can_use_pch_feature, $feature_name, $current_user ) {
	// If the VIP Support User plugin is not active, return the original value.
	// This prevents a fatal error when the plugin is not active, under certain conditions.
	// See https://github.com/Automattic/vip-go-mu-plugins/pull/6016
	if ( ! class_exists( 'Automattic\\VIP\\Support_User\\User' ) ) {
		return $current_user_can_use_pch_feature;
	}

	$user_id = $current_user->ID;

	if ( Support_User::user_has_vip_support_role( $user_id ) ) {
		return true;
	}

	return $current_user_can_use_pch_feature;
}, 999, 3 );
