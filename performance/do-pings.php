<?php

namespace Automattic\VIP\Performance;

// Disable pings by default
function disable_pings( $event ) {
	if ( 'do_pings' === $event->hook ) {
		return false;
	}

	return $event;
}
add_action( 'schedule_event', __NAMESPACE__ . '\disable_pings' );
