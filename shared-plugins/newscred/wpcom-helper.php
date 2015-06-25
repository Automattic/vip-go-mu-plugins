<?php

// Use our passthrough system to run all nc_mins_plugin_hook cron events through the WP.com jobs system.
// This allows certain functionality like the thumbnail download to work.
add_filter( 'wpcom_vip_passthrough_cron_to_jobs', function( $events ) {
		$events[] = 'nc_mins_plugin_hook';
		$events[] = 'nc_hourly_plugin_hook';

		return $events;
} );

