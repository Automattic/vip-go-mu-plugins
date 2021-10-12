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

declare(strict_types=1);

use Automattic\VIP\Parsely\Telemetry\Telemetry;
use Automattic\VIP\Parsely\Telemetry\Tracks;

require __DIR__ . '/Telemetry/class-telemetry.php';
require __DIR__ . '/Telemetry/class-telemetry-system.php';
require __DIR__ . '/Telemetry/Tracks/class-tracks.php';
require __DIR__ . '/Telemetry/Tracks/class-tracks-event.php';
add_action(
	'admin_init',
	function(): void {
		// If enabled, instantiating Telemetry with Automattic's Tracks backend
		if ( apply_filters( 'wp_parsely_enable_telemetry_backend', true ) ) {
			$tracks    = new Tracks();
			$telemetry = new Telemetry( $tracks );

			require_once __DIR__ . '/Telemetry/Events/wpparsely-settings-page-loaded.php';
			$telemetry->register_event(
				array(
					'action_hook' => 'load-settings_page_parsely',
					'callable'    => 'Automattic\VIP\Parsely\Telemetry\track_wpparsely_settings_page_loaded',
				)
			);

			require_once __DIR__ . '/Telemetry/Events/wpparsely-option-updated.php';
			$telemetry->register_event(
				array(
					'action_hook'   => 'update_option_parsely',
					'callable'      => 'Automattic\VIP\Parsely\Telemetry\track_wpparsely_option_updated',
					'accepted_args' => 2,
				)
			);

			require_once __DIR__ . '/Telemetry/Events/wpparsely-delete-widget.php';
			$telemetry->register_event(
				array(
					'action_hook'   => 'delete_widget',
					'callable'      => 'Automattic\VIP\Parsely\Telemetry\track_wpparsely_delete_widget',
					'accepted_args' => 3,
				)
			);

			require_once __DIR__ . '/src/Telemetry/Events/wpparsely-widget-updated.php';
			$telemetry->register_event(
				array(
					'action_hook'   => 'widget_update_callback',
					'callable'      => 'Parsely\Telemetry\track_wpparsely_widget_updated',
					'accepted_args' => 4,
					'is_filter'     => true,
				)
			);

			$telemetry->run();
		}
	}
);
