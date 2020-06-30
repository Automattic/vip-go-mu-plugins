<?php

namespace Automattic\VIP\Search;

class FieldCountGaugeJob {
	const CRON_EVENT_NAME = 'vip_config_sync_cron';

	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( \Automattic\VIP\Config\Sync::CRON_EVENT_NAME, [ $this, 'set_field_count_gauge' ] );
	}

	/**
	 * Set the field count gauge for posts for the current site 
	 */
	public function set_field_count_gauge() {
		\Automattic\VIP\Search\Search::instance()->set_field_count_gauge();
	}
}
