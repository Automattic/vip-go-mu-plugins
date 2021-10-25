<?php
/**
 * Tracking code for the `load-settings_page_parsely` event (whenever the Parse.ly settings page is loaded).
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * Records an event using the given Telemetry System when the Parse.ly settings page is loaded.
 *
 * @param Telemetry_System $telemetry_system
 * @return void
 */
function track_settings_page_loaded( Telemetry_System $telemetry_system ): void {
	if (
		! ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) ||
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] )
	) {
		return;
	}
	$telemetry_system->record_event( 'wpparsely_settings_page_loaded' );
}
