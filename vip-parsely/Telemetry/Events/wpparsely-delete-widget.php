<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

function track_wpparsely_delete_widget( $widget_id, $sidebar_id, $id_base, Telemetry_System $telemetry_system ): void {
	if ( WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID !== $id_base ) {
		return;
	}
	$telemetry_system->record_event( 'vip_wpparsely_delete_widget', compact( 'id_base' ) );
}
