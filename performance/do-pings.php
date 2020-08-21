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

// Don't allow new _encloseme metas
function block_encloseme_metadata_filter( $should_update, $object_id, $meta_key, $meta_value, $unique ) {
	if ( '_encloseme' === $meta_key ) {
		$should_update = false;
	}

	return $should_update;
}
add_filter( 'add_post_metadata', __NAMESPACE__ . '\block_encloseme_metadata_filter', 10, 5 );
