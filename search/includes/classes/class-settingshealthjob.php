<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Search\Health as Health;

require_once __DIR__ . '/class-health.php';

class SettingsHealthJob {

	/**
	 * The name of the scheduled cron event to run the health check
	 */
	const CRON_EVENT_NAME = 'vip_search_settings_health';

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
		$this->search     = $search;
		$this->health     = new Health( $search );
		$this->indexables = \ElasticPress\Indexables::factory();
	}

	/**
	 * Initialize the job class
	 *
	 * @access  public
	 */
	public function init() {
		// We always add this action so that the job can unregister itself if it no longer should be running
		add_action( self::CRON_EVENT_NAME, [ $this, 'check_settings_health' ] );

		$this->schedule_job();
	}

	/**
	 * Schedule settings health check job
	 */
	public function schedule_job() {
		if ( ! wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Disable health check job
	 */
	public function disable_job() {
		if ( wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_clear_scheduled_hook( self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Check settings health
	 */
	public function check_settings_health() {

		// Don't run the checks if the index is not built.
		if ( \ElasticPress\Utils\is_indexing() || ! \ElasticPress\Utils\get_last_sync() ) {
			return;
		}

		$unhealthy_indexables = $this->health->get_index_settings_health_for_all_indexables();

		if ( empty( $unhealthy_indexables ) ) {
			return;
		}

		$this->process_indexables_settings_health_results( $unhealthy_indexables );

		$this->heal_index_settings( $unhealthy_indexables );
	}

	public function process_indexables_settings_health_results( $results ) {
		// If the whole thing failed, error
		if ( is_wp_error( $results ) ) {
			$message = sprintf( 'Error while validating index settings for %s: %s', home_url(), $results->get_error_message() );

			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		foreach ( $results as $indexable_slug => $versions ) {
			// If there's an error, alert
			if ( is_wp_error( $versions ) ) {
				$message = sprintf( 'Error while validating index settings for indexable %s on %s: %s', $indexable_slug, home_url(), $versions->get_error_message() );

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );
			}

			// Each individual entry in $versions is an array of results, one per index version
			foreach ( $versions as $result ) {
				// Only alert if inconsistencies found
				if ( empty( $result['diff'] ) ) {
					continue;
				}

				$message = sprintf(
					'Index settings inconsistencies found for %s: (indexable: %s, index_version: %d, index_name: %s, diff: %s)',
					home_url(),
					$indexable_slug,
					$result['index_version'],
					$result['index_name'],
					var_export( $result['diff'], true )     // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				);

				$this->send_alert( '#vip-go-es-alerts', $message, 2, "{$indexable_slug}" );
			}
		}
	}

	public function heal_index_settings( $unhealthy_indexables ) {
		// If the whole thing failed, error
		if ( is_wp_error( $unhealthy_indexables ) ) {
			$message = sprintf( 'Error while attempting to heal index settings for %s: %s', home_url(), $unhealthy_indexables->get_error_message() );

			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		foreach ( $unhealthy_indexables as $indexable_slug => $versions ) {
			// If there's an error, alert
			if ( is_wp_error( $versions ) ) {
				$message = sprintf( 'Error while attempting to heal index settings for indexable %s on %s: %s', $indexable_slug, home_url(), $versions->get_error_message() );

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );

				continue;
			}

			$indexable = $this->indexables->get( $indexable_slug );

			if ( is_wp_error( $indexable ) || ! $indexable ) {
				$error_message = is_wp_error( $indexable ) ? $indexable->get_error_message() : 'Indexable not found';
				$message       = sprintf( 'Failed to load indexable %s when healing index settings on %s: %s', $indexable_slug, home_url(), $error_message );

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );

				continue;
			}

			// Each individual entry in $versions is an array of results, one per index version.
			foreach ( $versions as $result ) {
				// Only take action if there are actual inconsistencies in auto-healable keys.
				$diff = $this->health::limit_index_settings_to_keys( $result['diff'], $this->health::INDEX_SETTINGS_HEALTH_AUTO_HEAL_KEYS );
				if ( empty( $diff ) ) {
						continue;
				}

				$options = array();

				if ( isset( $result['index_version'] ) ) {
					$options['index_version'] = $result['index_version'];
				}

				$result = $this->health->heal_index_settings_for_indexable( $indexable, $options );

				if ( is_wp_error( $result['result'] ) ) {
					$message = sprintf( 'Failed to heal index settings for indexable %s and index version %d on %s: %s', $indexable_slug, $result['index_version'], home_url(), $result['result']->get_error_message() );

					$this->send_alert( '#vip-go-es-alerts', $message, 2 );

					continue;
				}

				$message = sprintf(
					'Index settings updated for %s: (indexable: %s, index_version: %d, index_name: %s)',
					home_url(),
					$indexable_slug,
					$result['index_version'] ?? '<missing index version>',
					$result['index_name'] ?? '<missing name>'
				);

				$this->send_alert( '#vip-go-es-alerts', $message, 2, "{$indexable_slug}" );
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
				$cron_interval = 1 * \HOUR_IN_SECONDS;
				// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
				wp_cache_set( $cache_key, 1, Cache::CACHE_GROUP_KEY, round( $cron_interval * 1.5 ) );
				return false;
			}

			wp_cache_delete( $cache_key, Cache::CACHE_GROUP_KEY );
		}

		return wpcom_vip_irc( $channel, $message, $level );
	}
}
