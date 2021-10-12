<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * Records an event when the Parse.ly
 *
 * @since 3.0.0
 *
 * @param Telemetry_System $telemetry_system
 * @return void
 */
function track_wpparsely_settings_page_loaded( Telemetry_System $telemetry_system ): void {
	if (
		! ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) ||
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] )
		// phpcs:enable
	) {
		return;
	}
	$telemetry_system->record_event( 'wpparsely_settings_page_loaded' );
}
