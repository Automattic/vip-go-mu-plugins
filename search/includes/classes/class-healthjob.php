<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Search\Health as Health;

require_once __DIR__ . '/class-health.php';

class HealthJob {

	/**
	 * The name of the scheduled cron event to run the health check
	 */
	const CRON_EVENT_NAME = 'vip_search_healthcheck';

	/**
	 * The name of the scheduled cron event to run the validate contnets check
	 */
	const CRON_EVENT_VALIDATE_CONTENT_NAME = 'vip_search_health_validate_content';

	/**
	 * Custom cron interval name
	 */
	const CRON_INTERVAL_NAME = 'vip_search_healthcheck_interval';

	/**
	 * Custom cron interval value
	 */
	const CRON_INTERVAL = 1 * \HOUR_IN_SECONDS;

	public $health_check_disabled_sites = array();

	/**
	 * Instance of the Health class
	 *
	 * Useful for overriding in tests via dependency injection
	 */
	public $health;

	/**
	 * Instance of Search class
	 *
	 * Useful for overriding (dependency injection) for tests
	 */
	public $search;

	/**
	 * Instance of \ElasticPress\Indexables
	 *
	 * Useful for overriding (dependency injection) for tests
	 */
	public $indexables;

	public function __construct( \Automattic\VIP\Search\Search $search ) {
		$this->search = $search;
		$this->health = new Health( $search );
		$this->indexables = \ElasticPress\Indexables::factory();
	}

	/**
	 * Initialize the job class
	 *
	 * @access  public
	 */
	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( self::CRON_EVENT_NAME, [ $this, 'check_health' ] );
		add_action( self::CRON_EVENT_VALIDATE_CONTENT_NAME, [ $this, 'validate_contents' ] );

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
		if ( ! wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL_NAME, self::CRON_EVENT_NAME );
		}
		if ( ! wp_next_scheduled( self::CRON_EVENT_VALIDATE_CONTENT_NAME ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_EVENT_VALIDATE_CONTENT_NAME );
		}
	}

	/**
	 * Disable health check job
	 *
	 * Remove the ES health check job from the events list
	 */
	public function disable_job() {
		if ( wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_clear_scheduled_hook( self::CRON_EVENT_NAME );
		}
		if ( wp_next_scheduled( self::CRON_EVENT_VALIDATE_CONTENT_NAME ) ) {
			wp_clear_scheduled_hook( self::CRON_EVENT_VALIDATE_CONTENT_NAME );
		}
	}

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add the custom interval to WP cron schedule
	 *
	 * @param       array   $schedule
	 *
	 * @return  mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ self::CRON_INTERVAL_NAME ] ) ) {
			return $schedule;
		}

		$schedule[ self::CRON_INTERVAL_NAME ] = [
			'interval' => self::CRON_INTERVAL,
			'display'  => __( 'VIP Search Healthcheck time interval' ),
		];

		return $schedule;
	}

	/**
	 * Check index health
	 */
	public function validate_contents() {
		// Check if job has been disabled
		if ( ! $this->is_enabled() ) {
			$this->disable_job();

			return;
		}

		// Don't run the checks if the index is not built.
		if ( \ElasticPress\Utils\is_indexing() || ! \ElasticPress\Utils\get_last_sync() ) {
			return;
		}

		$results = $this->health->validate_index_posts_content( [ 'silent' => true ] );

		if ( is_wp_error( $results ) ) {
			$message = sprintf( 'Cron validate-contents error for site %d (%s): %s', FILES_CLIENT_SITE_ID, home_url(), $results->get_error_message() );
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );
		} else if ( ! empty( $results ) ) {
			$message = sprintf( 'Cron validate-contents for site %d (%s): Autohealing executed for %d records.', FILES_CLIENT_SITE_ID, home_url(), count( $results ) );
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );
		}

	}

	/**
	 * Check index health
	 */
	public function check_health() {
		// Check if job has been disabled
		if ( ! $this->is_enabled() ) {
			$this->disable_job();

			return;
		}

		// Don't run the checks if the index is not built.
		if ( \ElasticPress\Utils\is_indexing() || ! \ElasticPress\Utils\get_last_sync() ) {
			return;
		}

		$this->check_document_count_health();
	}

	public function check_document_count_health() {
		$users_feature = \ElasticPress\Features::factory()->get_registered_feature( 'users' );

		if ( $users_feature instanceof \ElasticPress\Feature && $users_feature->is_active() ) {
			$users_indexable = \ElasticPress\Indexables::factory()->get( 'user' );

			$users_versions = $this->search->versioning->get_versions( $users_indexable );

			foreach ( $users_versions as $version ) {
				$user_results = Health::validate_index_users_count( array(
					'index_version' => $version['number'],
				) );

				$this->process_document_count_health_results( $user_results );
			}
		}

		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$posts_versions = $this->search->versioning->get_versions( $post_indexable );

		foreach ( $posts_versions as $version ) {
			$post_results = Health::validate_index_posts_count( array(
				'index_version' => $version['number'],
			) );

			$this->process_document_count_health_results( $post_results );
		}
	}

	/**
	 * Process the health check result
	 *
	 * @access  protected
	 * @param   array       $result     Array of results from Health index validation
	 */
	public function process_document_count_health_results( $results ) {
		// If the whole thing failed, error
		if ( is_wp_error( $results ) ) {
			$message = sprintf( 'Error while validating index for %s: %s', home_url(), $results->get_error_message() );

			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		foreach ( $results as $result ) {
			if ( array_key_exists( 'skipped', $result ) && $result['skipped'] ) {
				// We don't want to alert for skipped indexes
				continue;
			}

			// If there's an error, alert
			if ( array_key_exists( 'error', $result ) ) {
				$message = sprintf( 'Error while validating index for %s: %s', home_url(), $result['error'] );

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );
			}

			// Only alert if inconsistencies found
			if ( isset( $result['diff'] ) && 0 !== $result['diff'] ) {
				$message = sprintf(
					'Index inconsistencies found for %s: (entity: %s, type: %s, index_version: %d, DB count: %s, ES count: %s, Diff: %s)',
					home_url(),
					$result['entity'],
					$result['type'],
					$result['index_version'],
					$result['db_total'],
					$result['es_total'],
					$result['diff']
				);

				$this->send_alert( '#vip-go-es-alerts', $message, 2, "{$result['entity']}:{$result['type']}" );
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
	 * @param string $type content type
	 *
	 * @return bool Bool indicating if sending succeeded, failed or skipped
	 */
	public function send_alert( $channel, $message, $level, $type = '' ) {
		// We only want to send an alert if a consistency check didn't correct itself in two intervals.
		if ( $type ) {
			$cache_key = "healthcheck_alert_seen:{$type}";
			if ( false === wp_cache_get( $cache_key, Cache::CACHE_GROUP_KEY ) ) {
				wp_cache_set( $cache_key, 1, Cache::CACHE_GROUP_KEY, round( self::CRON_INTERVAL * 1.5 ) );
				return false;
			}

			wp_cache_delete( $cache_key, Cache::CACHE_GROUP_KEY );
		}

		return wpcom_vip_irc( $channel, $message, $level );
	}

	/**
	 * Is health check job enabled
	 *
	 * @return bool True if job is enabled. Else, false
	 */
	public function is_enabled() {
		if ( defined( 'DISABLE_VIP_SEARCH_HEALTHCHECKS' ) && true === DISABLE_VIP_SEARCH_HEALTHCHECKS ) {
			return false;
		}

		if ( defined( 'VIP_GO_APP_ID' ) ) {
			if ( in_array( VIP_GO_APP_ID, $this->health_check_disabled_sites, true ) ) {
				return false;
			}
		}

		$enabled_environments = apply_filters( 'vip_search_healthchecks_enabled_environments', array( 'production' ) );

		$enabled = in_array( VIP_GO_ENV, $enabled_environments, true );

		/**
		 * Filter whether to enable VIP search healthcheck
		 *
		 * @param bool $enable True to enable the healthcheck cron job
		 */
		return apply_filters( 'enable_vip_search_healthchecks', $enabled );
	}
}
