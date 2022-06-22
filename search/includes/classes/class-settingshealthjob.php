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
	const BUILD_LOCK_NAME = 'vip_search_cron_new_version_building_lock';

	/**
	 * The name of the option to store the last ID processed by the re-building job.
	 */
	const LAST_PROCESSED_ID_OPTION = 'vip_search_cron_last_processed_id';

	/**
	 * The name of the transient to store whether a re-building job is in progress.
	 */
	const BUILD_IN_PROGRESS_TRANSIENT = 'vip_search_cron_new_version_building_now';

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

		add_action( self::CRON_EVENT_BUILD_NAME, [ $this, 'build_new_index' ], 10, 2 );

		add_action( 'ep_cli_post_bulk_index', [ $this, 'update_last_processed_id' ], 10, 2 );

		// Clean-up last processed ID option
		add_action( 'ep_wp_cli_before_index', [ $this, 'delete_last_processed_id' ] );
		add_action( 'ep_wp_cli_after_index', [ $this, 'delete_last_processed_id' ] );

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
		$unhealthy_indexables = $this->health->get_index_settings_health_for_all_indexables();

		if ( empty( $unhealthy_indexables ) ) {
			return;
		}

		$this->process_indexables_settings_health_results( $unhealthy_indexables );
	}

	public function process_indexables_settings_health_results( $results ) {
		// If the whole thing failed, error
		if ( is_wp_error( $results ) ) {
			$message = sprintf(
				'Application %s: Error while validating index settings for %s: %s',
				FILES_CLIENT_SITE_ID,
				home_url(),
				$results->get_error_message()
			);

			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		foreach ( $results as $indexable_slug => $versions ) {
			// If there's an error, alert
			if ( is_wp_error( $versions ) ) {
				$message = sprintf(
					'Application %s: Error while validating index settings for indexable %s on %s: %s',
					FILES_CLIENT_SITE_ID,
					$indexable_slug,
					home_url(),
					$versions->get_error_message()
				);

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );
			}

			// Each individual entry in $versions is an array of results, one per index version
			foreach ( $versions as $result ) {
				// Only alert if inconsistencies found
				if ( empty( $result['diff'] ) ) {
					continue;
				}

				$indexable = $this->indexables->get( $indexable_slug );
				if ( isset( $result['diff']['index.number_of_shards'] ) && 1 === count( $result['diff'] )
					&& $this->search->versioning->get_active_version_number( $indexable ) === $result['index_version']
					&& false !== get_option( self::BUILD_LOCK_NAME ) ) {
						// Don't keep alerting if it's an active index in process of being re-built.
						continue;
				}

				$message = sprintf(
					'Application %s: Index settings inconsistencies found for %s: (indexable: %s, index_version: %d, index_name: %s, diff: %s)',
					FILES_CLIENT_SITE_ID,
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
				$message       = sprintf(
					'Application %s: Failed to load indexable %s when healing index settings on %s: %s',
					FILES_CLIENT_SITE_ID,
					$indexable_slug,
					home_url(),
					$error_message
				);

				$this->send_alert( '#vip-go-es-alerts', $message, 2 );

				continue;
			}

			// Each individual entry in $versions is an array of results, one per index version.
			foreach ( $versions as $result ) {
				if ( empty( $result['diff'] ) ) {
					continue;
				}
				// Check if active index needs to be re-built in the background.
				if ( isset( $result['diff']['index.number_of_shards'] ) && $this->search->versioning->get_active_version_number( $indexable ) === $result['index_version'] ) {
					$this->maybe_process_build( $indexable );
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
					$message = sprintf(
						'Application %s: Failed to heal index settings for indexable %s and index version %d on %s: %s',
						FILES_CLIENT_SITE_ID,
						$indexable_slug,
						$result['index_version'],
						home_url(),
						$result['result']->get_error_message(),
					);

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
	 * Store last processed post ID into option during bulk indexing operation.
	 *
	 * @param array $objects Objects being indexed
	 * @param array $response Elasticsearch bulk index response
	 *
	 * @return void
	 */
	public function update_last_processed_id( $objects, $response ) {
		if ( ! wp_doing_cron() ) {
			return;
		}

		update_option( self::LAST_PROCESSED_ID_OPTION, array_key_last( $objects ) );
		set_transient( self::BUILD_IN_PROGRESS_TRANSIENT, true, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Delete last processed post ID as part of clean-up.
	 *
	 * @return void
	 */
	public function delete_last_processed_id() {
		if ( ! wp_doing_cron() ) {
			return;
		}

		if ( false !== get_option( self::LAST_PROCESSED_ID_OPTION ) ) {
			delete_option( self::LAST_PROCESSED_ID_OPTION );
			delete_transient( self::BUILD_IN_PROGRESS_TRANSIENT );
		}
	}

	/**
	 * Determine whether to schedule an event to build new index as part of auto healing or resume
	 * it from where it left off (if the process has unexpectedly died).
	 *
	 * @param object $indexable The Indexable we want to rebuild.
	 */
	public function maybe_process_build( $indexable ) {
		$build_index_lock = get_option( self::BUILD_LOCK_NAME );
		if ( false !== $build_index_lock ) {
			// There's an on-going build in process, so we need to check how to process it.
			$process_build = $this->check_process_build();
			switch ( $process_build ) {
				case 'resume':
					// Indexing process was interrupted, let's restart it with the
					$last_processed_id = get_option( self::LAST_PROCESSED_ID_OPTION );
					if ( ! wp_next_scheduled( self::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $last_processed_id ] ) ) {
						wp_schedule_single_event( time() + 30, self::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $last_processed_id ] );
					}
					break;
				case 'swap':
					$this->swap_index_versions( $indexable );
					break;
				case 'in-progress':
				default:
					return;
			}
		} else {
			$current_versions = $this->search->versioning->get_versions( $indexable );
			if ( count( $current_versions ) > 1 ) {
				// Do not schedule new index build if index version limit is reached (2 versions per Indexable).
				$message = sprintf(
					'Application %s: Cannot automatically build new %s index on %s to meet shard requirements. Please ensure there is less than 2 index versions.',
					FILES_CLIENT_SITE_ID,
					$indexable->slug,
					home_url()
				);
				$this->send_alert( '#vip-go-es-alerts', $message, 2 );

				return;
			} elseif ( ! wp_next_scheduled( self::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] ) ) {
				wp_schedule_single_event( time() + 30, self::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
			}
		}
	}

	/**
	 * Build new index or resume index building in progress as part of auto healing to ensure shard requirements are met.
	 *
	 * @param string $indexable_slug The slug of the Indexable we want to rebuild.
	 * @param int|bool $last_processed_id The ID of the last indexed object. Defaults false.
	 */
	public function build_new_index( $indexable_slug, $last_processed_id = false ) {
		if ( false === $last_processed_id ) {
			add_option( self::BUILD_LOCK_NAME, time() ); // Set lock for starting build.
		}

		$indexable = $this->indexables->get( $indexable_slug );
		if ( ! $indexable ) {
			$indexable = new \WP_Error( 'indexable-not-found', sprintf( 'Indexable %s not found - is the feature active?', $indexable_slug ) );
		}
		if ( is_wp_error( $indexable ) ) {
			$message = sprintf(
				'Application %s: An error occurred during build of new %s index on %s for shard requirements: %s',
				FILES_CLIENT_SITE_ID,
				$indexable_slug,
				home_url(),
				$indexable->get_error_message()
			);
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		if ( false === $last_processed_id ) {
			// Only create new index version if it is a new build.
			$new_version = $this->search->versioning->add_version( $indexable );
			if ( is_wp_error( $new_version ) ) {
				$message = sprintf(
					'Application %s: An error occurred during adding new %s index on %s for shard requirements: %s',
					FILES_CLIENT_SITE_ID,
					$indexable_slug,
					home_url(),
					$new_version->get_error_message()
				);
				$this->send_alert( '#vip-go-es-alerts', $message, 2 );

				return;
			}
		}

		$new_version = $this->search->versioning->set_current_version_number( $indexable, 'next' );
		if ( is_wp_error( $new_version ) ) {
			$message = sprintf(
				'Application %s: An error occurred during setting new %s index on %s for shard requirements: %s',
				FILES_CLIENT_SITE_ID,
				$indexable_slug,
				home_url(),
				$new_version->get_error_message()
			);
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		// Do the indexing.
		$cmd        = new \ElasticPress\Command();
		$args       = [];
		$assoc_args = [
			'yes'        => true,
			'indexables' => $indexable_slug,
		];
		if ( $last_processed_id ) {
			$assoc_args['upper-limit-object-id'] = (int) $last_processed_id;
		}
		$cmd->index( $args, $assoc_args );

		update_option( self::LAST_PROCESSED_ID_OPTION, 'Indexing completed' );
	}

	/**
	 * Check the in-process build and determine how to handle it.
	 *
	 * @return string|bool Returns the next step: 'in-progress', 'resume', or 'swap'.
	 *
	 */
	protected function check_process_build() {
		$last_processed_id = get_option( self::LAST_PROCESSED_ID_OPTION );
		if ( 'Indexing completed' === $last_processed_id ) {
			return 'swap';
		}
		$in_progress = get_transient( self::BUILD_IN_PROGRESS_TRANSIENT );
		if ( false !== $in_progress ) {
			return 'in-progress';
		} elseif ( is_numeric( $last_processed_id ) ) {
			return 'resume';
		}

		return false;
	}

	/**
	 * Activate new index version and delete old one.
	 *
	 * @param object $Indexable Indexable object we want to swap out
	 */
	public function swap_index_versions( $indexable ) {
		$activate_version = $this->search->versioning->activate_version( $indexable, 'next' );
		if ( is_wp_error( $activate_version ) ) {
			$message = sprintf(
				'Application %s: An error occurred during activation of new %s index on %s for shard requirements: %s',
				FILES_CLIENT_SITE_ID,
				$indexable->slug,
				home_url(),
				$activate_version->get_error_message()
			);
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		$delete_version = $this->search->versioning->delete_version( $indexable, 'previous' );
		if ( is_wp_error( $delete_version ) ) {
			$message = sprintf(
				'Application %s: An error occurred during deletion of old %s index on %s for shard requirements: %s',
				FILES_CLIENT_SITE_ID,
				$indexable->slug,
				home_url(),
				$delete_version->get_error_message()
			);
			$this->send_alert( '#vip-go-es-alerts', $message, 2 );

			return;
		}

		\Automattic\VIP\Logstash\log2logstash(
			array(
				'severity' => 'info',
				'feature'  => 'search_versioning',
				'message'  => 'Built new index to meet shard requirements',
				'extra'    => [
					'homeurl'   => home_url(),
					'indexable' => $indexable->slug,
				],
			)
		);

		// Clean up
		$this->delete_last_processed_id();
		delete_option( self::BUILD_LOCK_NAME );
	}
}
