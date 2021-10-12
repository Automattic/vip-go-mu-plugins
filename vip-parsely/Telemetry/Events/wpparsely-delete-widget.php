<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * This is determined by our value passed to the `WP_Widget` constructor.
 *
 * @see https://github.com/Parsely/wp-parsely/blob/e9f1b8cd1a94743e068681a8106176d23857992d/src/class-parsely-recommended-widget.php#L28
 */
const WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID = 'parsely_recommended_widget';

function track_wpparsely_delete_widget( $widget_id, $sidebar_id, $id_base, Telemetry_System $telemetry_system ): void {
	if ( WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID !== $id_base ) {
		return;
	}
	$telemetry_system->record_event( 'vip_wpparsely_delete_widget', compact( 'id_base' ) );
}
