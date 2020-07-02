<?php

namespace Automattic\VIP\Search;

class QueueWaitTimeJob {
	const CRON_EVENT_NAME = 'vip_config_sync_cron';

	const FIELD_COUNT_GAUGE_DISABLED_SITES = array();

	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( self::CRON_EVENT_NAME, [ $this, 'set_queue_wait_time_gauge' ] );
	}

	/**
	 * Set the queue wait time gauge for posts for the current site 
	 */
	public function set_queue_wait_time_gauge() {
		\Automattic\VIP\Search\Search::instance()->set_queue_wait_time_gauge();
	}
}
