<?php

class QM_Cron_Collector extends QM_Collector {

	/**
	 * @var string
	 */
	public $id = 'qm-cron';

	/**
	 * Lists all crons that are defined in WP Core.
	 *
	 * @var array
	 *
	 * @internal To find all, search WP trunk for `wp_schedule_(single_)?event`.
	 */
	private $core_cron_hooks = [
		'do_pings',
		'importer_scheduled_cleanup',     // WP 3.1+.
		'publish_future_post',
		'update_network_counts',          // WP 3.1+.
		'upgrader_scheduled_cleanup',     // WP 3.3+.
		'wp_maybe_auto_update',           // WP 3.7+.
		'wp_scheduled_auto_draft_delete', // WP 3.4+.
		'wp_scheduled_delete',            // WP 2.9+.
		'wp_split_shared_term_batch',     // WP 4.3+.
		'wp_update_plugins',
		'wp_update_themes',
		'wp_version_check',
	];

	/**
	 * @return string
	 */
	public function name() {
		return esc_html__( 'Cron', 'query-monitor' );
	}

	/**
	 * @return void
	 */
	public function process() {
		$this->process_crons();
		$this->process_schedules();
	}

	/**
	 * Processes the cron jobs.
	 *
	 * @return void
	 */
	private function process_crons() {
		$this->data['doing_cron'] = get_transient( 'doing_cron' ) ? true : false;

		$crons = _get_cron_array();
		if ( is_array( $crons ) && ! empty( $crons ) ) {
			$this->data['crons'] = $crons;
			$this->sort_count_crons( $crons );
			$this->process_next_event_time( $crons );
		}
	}

	/**
	 * Sort and count crons.
	 *
	 * This function sorts the cron jobs into core crons, and custom crons. It also tallies
	 * a total count for the crons as this number is otherwise tough to get.
	 *
	 * @param array $crons Cron events
	 */
	private function sort_count_crons( $crons ) {
		$total_crons      = 0;
		$total_core_crons = 0;
		$total_user_crons = 0;
		$core_crons       = [];
		$user_crons       = [];
		foreach ( $crons as $time => $time_cron_array ) {
			foreach ( $time_cron_array as $hook => $data ) {
				$total_crons += count( $data );

				if ( in_array( $hook, $this->core_cron_hooks, true ) ) {
					$core_crons[ $time ][ $hook ] = $data;
					$total_core_crons            += count( $data );
				} else {
					$user_crons[ $time ][ $hook ] = $data;
					$total_user_crons            += count( $data );
				}
			}
		}

		$this->data['total_crons']      = $total_crons;
		$this->data['total_core_crons'] = $total_core_crons;
		$this->data['core_crons']       = $core_crons;
		$this->data['user_crons']       = $user_crons;
		$this->data['total_user_crons'] = $total_user_crons;
	}

	/**
	 * Process next cron event time
	 *
	 * @param array Cron events
	 */
	private function process_next_event_time( $crons ) {
		$cron_times          = array_keys( $crons );
		$unix_time_next_cron = (int) $cron_times[0];
		$time_next_cron      = gmdate( 'Y-m-d H:i:s', $unix_time_next_cron );

		$this->data['next_event_time']['human_time'] = $time_next_cron;
		$this->data['next_event_time']['unix']       = $unix_time_next_cron;
	}

	/**
	 * Sorting method for cron schedules. Order by schedules interval.
	 *
	 * @param array $schedule_a First element of comparison pair.
	 * @param array $schedule_b Second element of comparison pair.
	 *
	 * @return int Return 1 if $schedule_a argument 'interval' greater then $schedule_b argument 'interval',
	 *             0 if both intervals equivalent and -1 otherwise.
	 */
	private function schedules_sorting( $schedule_a, $schedule_b ) {
		return (int) $schedule_a['interval'] <=> (int) $schedule_b['interval'];
	}

	/**
	 * Process cron schedules
	 */
	private function process_schedules() {
		$schedules = wp_get_schedules();
		ksort( $schedules );
		uasort( $schedules, array( $this, 'schedules_sorting' ) );

		foreach ( $schedules as $interval_hook => $data ) {
			$interval = (int) $data['interval'];

			$this->data['schedules'][ $interval_hook ] = [
				'interval' => $interval,
				'display'  => $data['display'],
			];
		}
	}

	/**
	 * Transform a time in seconds to minutes rounded to 2 decimals.
	 *
	 * @param int $time Unix timestamp.
	 *
	 * @return float
	 */
	private function get_minutes( $time ) {
		return round( ( (int) $time / 60 ), 2 );
	}

	/**
	 * Transform a time in seconds to hours rounded to 2 decimals.
	 *
	 * @param int $time Unix timestamp.
	 *
	 * @return float
	 */
	private function get_hours( $time ) {
		return round( ( (int) $time / 3600 ), 2 );
	}
}
