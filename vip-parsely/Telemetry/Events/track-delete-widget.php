<?php
/**
 * Tracking code for the `update_option_parsely` event (whenever the Parse.ly option, usually in the Parse.ly
 * settings page, is updated).
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * Records an event using the given Telemetry System whenever a `parsely_recommended_widget` instance is deleted.
 *
 * @param string $widget_id ID of the widget marked for deletion.
 * @param string $sidebar_id ID of the sidebar the widget was deleted from.
 * @param string $id_base ID base for the widget.
 * @param Telemetry_System $telemetry_system
 * @return void
 */
function track_delete_widget( string $widget_id, string $sidebar_id, string $id_base, Telemetry_System $telemetry_system ): void {
	if ( WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID !== $id_base ) {
		return;
	}
	$telemetry_system->record_event( 'wpparsely_delete_widget', compact( 'id_base' ) );
}
