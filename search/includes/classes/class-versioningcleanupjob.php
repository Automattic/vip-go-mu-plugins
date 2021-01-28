<?php

namespace Automattic\VIP\Search;

class VersioningCleanupJob {

	/**
	 * The name of the scheduled cron event to run the versioning cleanup.
	 */
	const CRON_EVENT_NAME = 'vip_search_versioning_cleanup';

	const WEEK_IN_SECONDS = 7 * 24 * 60 * 60;

	const SEARCH_ALERT_SLACK_CHAT = '#vip-go-es-alerts';

	public function __construct( $indexables, $versioning ) {
		$this->indexables = $indexables;
		$this->versioning = $versioning;
	}

	/**
	 * Initialize the job class.
	 *
	 * @access  public
	 */
	public function init() {
		add_action( self::CRON_EVENT_NAME, [ $this, 'versioning_cleanup' ] );

		$this->schedule_job();
	}

	/**
	 * Schedule class versioning job.
	 *
	 * Add the event name to WP cron schedule and then add the action.
	 */
	public function schedule_job() {
		if ( ! wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_EVENT_NAME );
		}
	}

	/**
	 * Process versioning cleanup.
	 */
	public function versioning_cleanup() {
		$indexables = $this->indexables->get_all();

		foreach ( $indexables as $indexable ) {
			$inactive_versions = $this->get_stale_inactive_versions( $indexable );

			foreach ( $inactive_versions as $version ) {
				if ( ! ( $version['active'] ?? false ) ) {
					// double check that it is not active
					$this->send_notification( $indexable->slug, $version['number'] );
				}
			}
		}
	}


	/**
	 * Retrieve inactive versions to be deleted
	 *
	 * @param \ElasticPress\Indexable $indexable The Indexable for which to retrieve index versions
	 * @return array Array of inactive index versions
	 */
	public function get_stale_inactive_versions( \ElasticPress\Indexable $indexable ) {
		$versions = $this->versioning->get_versions( $indexable );

		if ( ! $versions && ! is_array( $versions ) ) {
			return [];
		}

		$active_version = $this->versioning->get_active_version( $indexable );

		if ( ! $active_version || ! $active_version['activated_time'] ) {
			// No active version or active version doesn't have activated time making it impossible to determine if it was activated recently or not
			return [];
		}

		$week_ago_breakpoint            = time() - self::WEEK_IN_SECONDS;
		$was_activated_in_the_last_week = $active_version['activated_time'] > $week_ago_breakpoint;
		if ( $was_activated_in_the_last_week ) {
			return [];
		}

		$inactive_versions = [];
		foreach ( $versions as $version ) {
			if ( $version['active'] ?? false ) {
				continue;
			}

			if ( ( $version['created_time'] ?? time() ) > $week_ago_breakpoint ) {
				// If the version was created within last week OR doen't have created_time defined it is not inactive
				continue;
			}

			array_push( $inactive_versions, $version );
		}

		return $inactive_versions;
	}

	/**
	 * Send a notification about version to be deleted
	 *
	 * @param string $indexable_slug The slug of indexable
	 * @param int $version The version number
	 *
	 * @return bool Bool indicating if sending succeeded, failed or skipped
	 */
	public function send_notification( $indexable_slug, $version ) {

		$message = sprintf(
			"Application %d - %s we would delete inactive index version %s for '%s' indexable",
			FILES_CLIENT_SITE_ID,
			home_url(),
			$version,
			$indexable_slug
		);

		\Automattic\VIP\Logstash\log2logstash(
			array(
				'severity' => 'info',
				'feature'  => 'vip_search_versioning',
				'message'  => $message,
			)
		);

		\Automattic\VIP\Utils\Alerts::chat( self::SEARCH_ALERT_SLACK_CHAT, $message );
	}
}
