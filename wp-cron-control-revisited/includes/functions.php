<?php

namespace WP_Cron_Control_Revisited;

/**
 * Retrieve a plugin variable
 */
function get_plugin_var( $variable ) {
	return property_exists( Main::instance(), $variable ) ? Main::instance()->$variable : null;
}

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
