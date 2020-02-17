<?php

namespace Automattic\VIP\Elasticsearch;

use Automattic\VIP\Elasticsearch\Health as Health;

require_once __DIR__ . '/class-health.php';

class HealthJob {

	/**
	 * The name of the scheduled cron event to run the health check
	 */
	const CRON_EVENT_NAME = 'vip_search_healthcheck';

	/**
	 * Custom cron interval name
	 */
	const CRON_INTERVAL_NAME = 'vip_search_healthcheck_interval';

	/**
	 * Custom cron interval value
	 */
	const CRON_INTERVAL = 60 * 30; // 30 minutes in seconds

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

		$schedule[ self::CRON_INTERVAL_NAME ] = [
			'interval' => self::CRON_INTERVAL,
			'display' => __( 'VIP Search Healthcheck time interval' ),
		];

		return $schedule;
	}

	/**
	 * Check ElasticSearch index health
	 */
	public function check_health() {
		$user_results = Health::validate_index_users_count();

		$this->process_results( $user_results );

		$post_results = Health::validate_index_posts_count();

		$this->process_results( $post_results );
	}

	/**
	 * Process the health check result
	 *
	 * @access	protected
	 * @param	array		$result		Array of results from Health index validation
	 */
	protected function process_results( $results ) {
		// If the whole thing failed, error
		if( is_wp_error( $results ) ) {
			$message = sprintf( 'Error while validating index for %s: %s', home_url(), $results->get_error_message() );

			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		foreach( $results as $result ) {
			// If there's an error, alert
			if( array_key_exists( 'error', $result ) ) {
				$message = sprintf( 'Error while validating index for %s: %s', home_url(), $result['error'] );

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );
			}

			// Only alert if inconsistencies found
			if ( isset( $result[ 'diff' ] ) && 0 !== $result[ 'diff' ] ) {
				$message = sprintf(
					'Index inconsistencies found for %s: (entity: %s, type: %s, DB count: %s, ES count: %s, Diff: %s)',
					home_url(),
					$result[ 'entity' ],
					$result[ 'type' ],
					$result[ 'db_total' ],
					$result[ 'es_total' ],
					$result[ 'diff' ]
				);

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );	
			}
		}
	}

	/**
	 * Send an alert
	 * 
	 * @see wpcom_vip_irc()
	 * 
	 * @param string $channel IRC / Slack channel to send message to
	 * @param string $message The message to send
	 * @param int $level Alert level
	 * 
	 * @return bool Bool indicating if sending succeeded or failed
	 */
	public function send_alert( $channel, $message, $level ) {
		return wpcom_vip_irc( $channel, $message, $level );
	}
}
