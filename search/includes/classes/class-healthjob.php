<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Search\Health as Health;
use Automattic\VIP\Utils\Alerts;

require_once __DIR__ . '/class-health.php';

class HealthJob {

	/**
	 * The name of the scheduled cron event to run the validate contnets check
	 */
	const CRON_EVENT_VALIDATE_CONTENT_NAME = 'vip_search_health_validate_content';

	/**
	 * @var int the number after which the alert should be sent for inconsistencies found.
	 */
	const INCONSISTENCIES_ALERT_THRESHOLD = 50;

	/**
	 * @var int the percentage after which the alert should be sent for autoheal - 0.1 = 10%
	 */
	const AUTOHEALED_ALERT_THRESHOLD = 0.1;

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
		add_action( self::CRON_EVENT_VALIDATE_CONTENT_NAME, [ $this, 'validate_contents' ] );

		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->schedule_job();
	}

	/**
	 * Schedule health check job
	 *
	 * Add the event name to WP cron schedule and then add the action
	 */
	public function schedule_job() {
		if ( ! wp_next_scheduled( self::CRON_EVENT_VALIDATE_CONTENT_NAME ) ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions.rand_mt_rand
			wp_schedule_event( time() + ( mt_rand( 1, 7 ) * DAY_IN_SECONDS ) + ( mt_rand( 1, 24 ) * HOUR_IN_SECONDS ), 'weekly', self::CRON_EVENT_VALIDATE_CONTENT_NAME );
		}

		if ( wp_next_scheduled( 'vip_search_healthcheck' ) ) {
			wp_clear_scheduled_hook( 'vip_search_healthcheck' );
		}
	}

	/**
	 * Disable health check job
	 *
	 * Remove the ES health check job from the events list
	 */
	public function disable_job() {
		if ( wp_next_scheduled( self::CRON_EVENT_VALIDATE_CONTENT_NAME ) ) {
			wp_clear_scheduled_hook( self::CRON_EVENT_VALIDATE_CONTENT_NAME );
		}
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

		$post_indexable = $this->indexables->get( 'post' );
		// Don't run the checks if the index is not built or is indexing.
		if ( ! $post_indexable || ! $post_indexable->index_exists() || \ElasticPress\Utils\is_indexing() ) {
			return;
		}

		\Automattic\VIP\Logstash\log2logstash(
			array(
				'severity' => 'info',
				'feature'  => 'search_content_validation',
				'message'  => 'Post content validation started',
				'extra'    => [
					'homeurl' => home_url(),
				],
			)
		);

		$results = $this->health->validate_index_posts_content( [ 'silent' => true ] );

		\Automattic\VIP\Logstash\log2logstash(
			array(
				'severity' => 'info',
				'feature'  => 'search_content_validation',
				'message'  => 'Post content validation completed',
				'extra'    => [
					'homeurl'    => home_url(),
					'index_name' => $post_indexable->get_index_name(),
				],
			)
		);

		if ( is_wp_error( $results ) && 'es_content_validation_already_ongoing' !== $results->get_error_code() ) {
			$message = sprintf( 'Cron validate-contents error for site %d (%s): %s', FILES_CLIENT_SITE_ID, home_url(), $results->get_error_message() );
			Alerts::chat( '#vip-go-es-alerts', $message, 2 );
		}

	}

	/**
	 * Is health check job enabled
	 *
	 * @return bool True if job is enabled. Else, false
	 */
	public function is_enabled() {
		if ( defined( 'DISABLE_VIP_SEARCH_HEALTHCHECKS' ) && true === constant( 'DISABLE_VIP_SEARCH_HEALTHCHECKS' ) ) {
			return false;
		}

		if ( defined( 'VIP_GO_APP_ID' ) && in_array( constant( 'VIP_GO_APP_ID' ), $this->health_check_disabled_sites, true ) ) {
			return false;
		}

		$enabled_environments = apply_filters( 'vip_search_healthchecks_enabled_environments', array( 'production' ) );

		$enabled = in_array( constant( 'VIP_GO_ENV' ), $enabled_environments, true );

		/**
		 * Filter whether to enable VIP search healthcheck
		 *
		 * @param bool $enable True to enable the healthcheck cron job
		 */
		return apply_filters( 'enable_vip_search_healthchecks', $enabled );
	}
}
