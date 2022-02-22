<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Search\Health as Health;
use Automattic\VIP\Utils\Alerts;
use \WP_CLI;

require_once __DIR__ . '/class-health.php';

class SettingsHealthJob {

	/**
	 * The name of the scheduled cron event to run the health check
	 */
	const CRON_EVENT_NAME = 'vip_search_settings_health';

	/**
	 * The name of the scheduled cron event to build the new index version out to meet minimum shard requirements
	 */
	const CRON_EVENT_BUILD_NAME = 'vip_search_build_new_index_version';

	/**
	 * The name of the option lock when an new index version is already being built
	 */
	const BUILD_LOCK_NAME = 'vip_search_new_version_building_lock';

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

		add_action( self::CRON_EVENT_BUILD_NAME, [ $this, 'build_new_index' ], 10, 1 );

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

		$this->heal_index_settings( $results );
	}

	public function heal_index_settings( $unhealthy_indexables ) {
		foreach ( $unhealthy_indexables as $indexable_slug => $versions ) {
			if ( is_wp_error( $versions ) ) {
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
				if ( empty( $result['diff'] ) ) {
					continue;
				}
				// Check if index needs to be re-built in the background.
				if ( true === array_key_exists( 'index.number_of_shards', $result['diff'] ) ) {
					$this->maybe_schedule_build_new_index( $indexable );
				}

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
				}
			}
		}
	}

	/**
	 * Send an alert
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

		return Alerts::chat( $channel, $message, $level );
	}

	/**
	 * Determine whether to schedule event to build new index as part of auto healing.
	 *
	 * @param object $indexable The Indexable we want to rebuild.
	 */
	public function maybe_schedule_build_new_index( $indexable ) {
		// Only do for non-production for now.
		$is_prod = defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' === VIP_GO_APP_ENVIRONMENT;
		if ( $is_prod ) {
			return;
		}
		
		// Bail if new index build is already occurring.
		$new_index_lock = get_option( self::BUILD_LOCK_NAME );
		if ( false !== $new_index_lock ) {
			return;
		}

		// Do not schedule new index build if index version limit is reached (2 versions per Indexable).
		$current_versions = $this->search->versioning->get_versions( $indexable );
		if ( count( $current_versions ) > 1 ) {
			$message = sprintf(
				'Cannot automatically build new %s index on %s to meet shard requirements. Please ensure there is less than 2 index versions.',
				$indexable->slug,
				home_url()
			);
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );
			
			return;
		} elseif ( ! wp_next_scheduled( self::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		}
	}

	/**
	 * Build new index and hot-swap it afterwards as part of auto healing to ensure shard requirements are met.
	 *
	 * @param string $indexable_slug The slug of the Indexable we want to rebuild.
	 */
	public function build_new_index( $indexable_slug ) {
		update_option( self::BUILD_LOCK_NAME, time(), false ); // Set lock for starting rebuild.

		$indexable = $this->indexables->get( $indexable_slug );
		if ( ! $indexable ) {
			$indexable = new \WP_Error( 'indexable-not-found', sprintf( 'Indexable %s not found - is the feature active?', $indexable_slug ) );
		}
		if ( is_wp_error( $indexable ) ) {
			$message = sprintf( 'An error occurred during build of new %s index on %s for shard requirements: %s', $indexable_slug, home_url(), $indexable->get_error_message() );
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}
		$new_version = $this->search->versioning->add_version( $indexable );
		if ( is_wp_error( $new_version ) ) {
			$message = sprintf( 'An error occurred during build of new %s index on %s for shard requirements: %s', $indexable_slug, home_url(), $new_version->get_error_message() );
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		$new_version = $this->search->versioning->set_current_version_number( $indexable, 'next' );
		if ( is_wp_error( $new_version ) ) {
			$message = sprintf( 'An error occurred during build of new %s index on %s for shard requirements: %s', $indexable_slug, home_url(), $new_version->get_error_message() );
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		// Delete CLI option for tracking command successfully finished
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_option( 'ep_last_cli_index' );
		} else {
			delete_option( 'ep_last_cli_index' );
		}

		// Do the indexing.
		$cmd        = new \ElasticPress\Command();
		$args       = [];
		$assoc_args = [
			'yes'        => true,
			'indexables' => $indexable_slug,
		];
		$cmd->index( $args, $assoc_args );

		$option = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ? get_site_option( 'ep_last_cli_index' ) : get_option( 'ep_last_cli_index' );
		if ( $option ) {
			$activate_version = $this->search->versioning->activate_version( $indexable, 'next' );
			if ( is_wp_error( $activate_version ) ) {
				$message = sprintf( 'An error occurred during activation of new %s index on %s for shard requirements: %s', $indexable_slug, home_url(), $activate_version->get_error_message() );
				$this->send_alert( '#vip-go-es-alerts', $message, 2 );
	
				return;
			}
			
			$delete_version = $this->search->versioning->delete_version( $indexable, 'previous' );
			if ( is_wp_error( $versioning ) ) {
				$message = sprintf( 'An error occurred during deletion of old %s index on %s for shard requirements: %s', $indexable_slug, home_url(), $delete_version->get_error_message() );
				$this->send_alert( '#vip-go-es-alerts', $message, 2 );
	
				return;
			}

			$message = sprintf( 'Successfully built new %s index for shard requirements on %s.', $indexable_slug, home_url() );
		} else {
			$message = sprintf( 'Build failure of new %s index on %s for shard requirements!', $indexable_slug, home_url() );
		}
		$this->send_alert( '#vip-go-es-alerts', $message, 2 );

		delete_option( self::BUILD_LOCK_NAME ); // Remove lock
	}
}
