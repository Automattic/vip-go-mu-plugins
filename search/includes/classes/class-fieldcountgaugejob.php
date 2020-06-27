<?php

namespace Automattic\VIP\Search;

class FieldCountGaugeJob {
	/**
	 * The name of the scheduled cron event to run the gauge setting
	 */
	const CRON_EVENT_NAME = 'vip_search_field_count_gauge_set';

	/**
	 * Custom cron interval name
	 */
	const CRON_INTERVAL_NAME = 'vip_search_field_count_gauge_interval';

	/**
	 * Custom cron interval value
	 */
	const CRON_INTERVAL = 1 * \DAY_IN_SECONDS; // 30 minutes in seconds

	const FIELD_COUNT_GAUGE_DISABLED_SITES = array();

	/**
	 * Initialize the job class
	 *
	 * @access public
	 */
	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( self::CRON_EVENT_NAME, [ $this, 'set_field_count_gauge' ] );

		if ( ! $this->is_enabled() ) {
			return;
		}

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
		if ( ! \wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			\wp_schedule_event( time(), self::CRON_INTERVAL_NAME, self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Disable health check job
	 *
	 * Remove the ES health check job from the events list
	 */
	public function disable_job() {
		if ( \wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			\wp_clear_scheduled_hook( self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add the custom interval to WP cron schedule
	 *
	 * @param array $schedule
	 *
	 * @return mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ self::CRON_INTERVAL_NAME ] ) ) {
			return $schedule;
		}

		$schedule[ self::CRON_INTERVAL_NAME ] = [
			'interval' => self::CRON_INTERVAL,
			'display' => __( 'VIP Search set field count gauge time interval' ),
		];

		return $schedule;
	}

	/**
	 * Is health check job enabled
	 *
	 * @return bool True if job is enabled. Else, false
	 */
	public function is_enabled() {
		if ( defined( 'DISABLE_VIP_SEARCH_FIELD_COUNT_GAUGE' ) && true === DISABLE_VIP_SEARCH_FIELD_COUNT_GAUGE ) {
			return false;
		}

		if ( defined( 'VIP_GO_APP_ID' ) ) {
			if ( in_array( VIP_GO_APP_ID, self::FIELD_COUNT_GAUGE_DISABLED_SITES, true ) ) {
				return false;
			}
		}

		$enabled_environments = apply_filters( 'vip_search_field_count_gauge_enabled_environments', array( 'production' ) );

		$enabled = in_array( VIP_GO_ENV, $enabled_environments, true );

		// Don't run the checks if the index is not built.
		if ( \ElasticPress\Utils\is_indexing() || ! \ElasticPress\Utils\get_last_sync() ) {
			$enabled = false;
		}

		/**
		 * Filter whether to enable VIP search healthcheck
		 *
		 * @param bool $enable True to enable the healthcheck cron job
		 */
		return apply_filters( 'enable_vip_search_field_count_gauge', $enabled );
	}

	/**
	 * Set the field count gauge for posts for the current site 
	 */
	public function set_field_count_gauge() {
		\Automattic\VIP\Search\Search::instance()->set_field_count_gauge();
	}
}
