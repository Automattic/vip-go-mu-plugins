<?php

namespace Automattic\VIP\Cron;

if ( file_exists( __DIR__ . '/action-scheduler-dynamic-queue.php' ) ) {
	require_once __DIR__ . '/action-scheduler-dynamic-queue.php';

	// Priority 9 required to be in time for setting the cron-control concurrency whitelist.
	// We also need to give time for plugins to load up Action Scheduler.
	add_action( 'after_setup_theme', function() {
		( new Action_Scheduler_Dynamic_Queue() )->init();
	}, 9 );
}
