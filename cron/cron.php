<?php

namespace Automattic\VIP\Cron;

// Set up the auto-scaling mechanics for action scheduler.
if ( file_exists( __DIR__ . '/action-scheduler-dynamic-queue.php' ) ) {
	require_once __DIR__ . '/action-scheduler-dynamic-queue.php';

	// Priority 9 required to be in time for setting the cron-control concurrency whitelist.
	// We also need to give time for plugins to load up Action Scheduler.
	add_action( 'after_setup_theme', function () {
		( new Action_Scheduler_Dynamic_Queue() )->init();
	}, 9 );
}

// Unregister Jetpack-related cron events when disabled.
add_action( 'cli_init', function () {
	$jetpack_is_disabled  = defined( 'VIP_JETPACK_SKIP_LOAD' ) && true === VIP_JETPACK_SKIP_LOAD;
	$events_to_unregister = [];

	if ( $jetpack_is_disabled ) {
		$events_to_unregister = array_merge( $events_to_unregister, [
			'jetpack_sync_cron',
			'jetpack_sync_full_cron',
			'jetpack_clean_nonces',
			'jetpack_waf_rules_update_cron',
			'jetpack_v2_heartbeat',
		] );
	}

	// When/if the cron event hook runs, unschedule the event so it runs no more.
	foreach ( $events_to_unregister as $event_hook ) {
		add_action( $event_hook, fn() => wp_unschedule_hook( $event_hook ) );
	}
} );


add_action( 'cli_init', '\Automattic\VIP\Cron\vip_schedule_aggregated_cron' );

function vip_schedule_aggregated_cron() {
	if ( defined( 'WP_INSTALLING' ) && true === constant( 'WP_INSTALLING' ) ) {
		return;
	}

	if ( wp_next_scheduled( 'vip_aggregated_cron_hourly' ) ) {
		return;
	}

	$timestamp = time();
	$offset    = 0;

	// To avoid piling up events on the same time, we offset the cron event using the following formula:
	// INTERVAL / TOTAL_SITES * SITE_ID
	if ( is_multisite() && wp_count_sites()['all'] > 1 ) {
		$slot   = HOUR_IN_SECONDS / wp_count_sites()['all'];
		$offset = $slot * get_current_blog_id();
	}

	wp_schedule_event( $timestamp + $offset, 'hourly', 'vip_aggregated_cron_hourly' );
}

// Watch for deprecation usage.
if ( ! defined( 'CRON_CONTROL_WARN_FOR_DEPRECATIONS' ) ) {
	define( 'CRON_CONTROL_WARN_FOR_DEPRECATIONS', true );
}
