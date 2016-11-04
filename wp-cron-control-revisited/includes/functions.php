<?php

namespace WP_Cron_Control_Revisited;

/**
 * Retrieve a plugin variable
 */
function get_plugin_var( $variable ) {
	return property_exists( Main::instance(), $variable ) ? Main::instance()->$variable : null;
}
