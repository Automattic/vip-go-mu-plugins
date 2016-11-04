<?php

namespace WP_Cron_Control_Revisited;

/**
 * Retrieve a plugin variable
 */
function get_plugin_var( $variable ) {
	return property_exists( Main::instance(), $variable ) ? Main::instance()->$variable : null;
}

/**
 * Delete an event
 *
 * @param $timestamp  int     Unix timestamp
 * @param $action     string  name of action used when the event is registered (unhashed)
 * @param $instance   string  md5 hash of the event's arguments array, which Core uses to index the `cron` option
 *
 * @return bool
 */
function delete_cron_event( $timestamp, $action, $instance ) {
	return Cron_Options_CPT::instance()->delete_event( $timestamp, $action, $instance );
}
