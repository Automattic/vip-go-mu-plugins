<?php

namespace Automattic\VIP\Cron;

// Set up the auto-scaling mechanics for action scheduler.
if ( file_exists( __DIR__ . '/action-scheduler-dynamic-queue.php' ) ) {
	require_once __DIR__ . '/action-scheduler-dynamic-queue.php';

	// Priority 9 required to be in time for setting the cron-control concurrency whitelist.
	// We also need to give time for plugins to load up Action Scheduler.
	add_action( 'after_setup_theme', function() {
		( new Action_Scheduler_Dynamic_Queue() )->init();
	}, 9 );
}

// Unregister Jetpack-related cron events when disabled.
add_action( 'cli_init', function() {
	$jetpack_is_disabled    = defined( 'VIP_JETPACK_SKIP_LOAD' ) && true === VIP_JETPACK_SKIP_LOAD;
	$vaultpress_is_disabled = $jetpack_is_disabled || ( defined( 'VIP_VAULTPRESS_SKIP_LOAD' ) && true === VIP_VAULTPRESS_SKIP_LOAD );

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

	if ( $vaultpress_is_disabled ) {
		$events_to_unregister = array_merge( $events_to_unregister, [ 'vp_scan_site', 'vp_scan_next_batch' ] );
	}

	// When/if the cron event hook runs, unschedule the event so it runs no more.
	foreach ( $events_to_unregister as $event_hook ) {
		add_action( $event_hook, fn() => wp_unschedule_hook( $event_hook ) );
	}
} );
