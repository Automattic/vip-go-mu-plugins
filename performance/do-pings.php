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

function pre_disable_pings( $scheduled, $event ) {
	if ( null !== $scheduled ) {
		return $scheduled;
	}
	
	if ( 'do_pings' === $event->hook ) {
		return false;
	}
	
	return $scheduled;
}
// Hooking at 0 to get in before cron control on pre_schedule_event
add_filter( 'pre_schedule_event', __NAMESPACE__ . '\pre_disable_pings', 0, 2 );

// Don't allow new _encloseme metas
function block_encloseme_metadata_filter( $should_update, $object_id, $meta_key ) {
	if ( '_encloseme' === $meta_key ) {
		$should_update = false;
	}

	return $should_update;
}
add_filter( 'add_post_metadata', __NAMESPACE__ . '\block_encloseme_metadata_filter', 10, 3 );
