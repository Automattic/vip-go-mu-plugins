<?php

namespace Automattic\VIP\Performance;

// Disable pings by default
function disable_pings( $event ) {
	// Already blocked, carry on
	if ( ! is_object( $event ) ) {
		return $event;
	}

	if ( 'do_pings' === $event->hook ) {
		return false;
	}

	return $event;
}
add_action( 'schedule_event', __NAMESPACE__ . '\disable_pings' );
