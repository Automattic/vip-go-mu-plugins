<?php

namespace Automattic\VIP\Search;

require_once __DIR__ . '/../../../config/class-sync.php';

class FieldCountGaugeJob {
	const CRON_EVENT_NAME = 'vip_config_sync_cron';

	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( \Automattic\VIP\Config\Sync::CRON_EVENT_NAME, [ $this, 'maybe_alert_for_field_count' ] );
	}

	/**
	 * Set the field count gauge for posts for the current site
	 */
	public function maybe_alert_for_field_count() {
		\Automattic\VIP\Search\Search::instance()->maybe_alert_for_field_count();
	}
}
