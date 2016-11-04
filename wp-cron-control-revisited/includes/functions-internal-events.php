<?php

namespace WP_Cron_Control_Revisited;

/**
 * Check if an event is an internal one that the plugin will always run
 */
function is_internal_event( $action ) {
	return Internal_Events::instance()->is_internal_event( $action );
}

/**
 * Check if an event should never run
 */
function is_blocked_event( $action ) {
	return Internal_Events::instance()->is_blocked_event( $action );
}
