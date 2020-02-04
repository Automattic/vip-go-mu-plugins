<?php

namespace Automattic\VIP\Elasticsearch;

use Automattic\VIP\Elasticsearch\Health as Health;

class HealthJob {

	/**
	 * The name of the scheduled cron event to run the health check
	 */
	const CRON_EVENT_NAME = 'vip_search_healthcheck';

	/**
	 * Custom cron interval name
	 */
	const CRON_INTERVAL_NAME = 'vip_half_hour';

	/**
	 * Custom cron interval value
	 *
	 * 30 minutes in seconds
	 */
	const CRON_INTERVAL = 60 * 30;

	/**
	 * Initialize the job class
	 *
	 * @access	public
	 */
	public function init() {
		// Add the custom cron schedule
		add_filter( 'cron_schedules', [ $this, 'filter_cron_schedules' ], 10, 1 );

		$this->schedule_job();
	}

	/**
	 * Schedule health check job
	 *
	 * Add the event name to WP cron schedule and then add the action
	 */
	public function schedule_job() {
		if ( ! wp_next_scheduled( self::CRON_EVENT_NAME )  ) {
			wp_schedule_event( time(), self::CRON_INTERVAL_NAME, self::CRON_EVENT_NAME );
		}

		add_action( self::CRON_EVENT_NAME, [ $this, 'check_health' ] );
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add the custom interval to WP cron schedule
	 *
	 * @param		array	$schedule
	 *
	 * @return	mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ self::CRON_INTERVAL_NAME ] ) ) {
			return $schedule;
		}

		// Not actually five minutes; we want it to run faster though to get through everything.
		$schedule[ self::CRON_INTERVAL_NAME ] = [
			'interval' => CRON_INTERVAL,
			'display' => __( 'Once every 30 minutes' ),
		];

		return $schedule;
	}

	/**
	 * Check ElasticSearch index health
	 */
	public function check_health() {
		$user_results = Health::validate_index_users_count();

		$post_results = Health::validate_index_posts_count();
	}
}
