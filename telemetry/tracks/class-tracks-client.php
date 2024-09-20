<?php
/**
 * Telemetry: Tracks Client class
 *
 * @package Automattic\VIP\Telemetry\Tracks
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use WP_Error;
use WP_Http;
use function Automattic\VIP\Logstash\log2logstash;

/**
 * Handles all operations related to the Tracks API.
 */
class Tracks_Client {
	/**
	 * Tracks REST API endpoint for post requests
	 */
	protected const TRACKS_ENDPOINT = 'https://public-api.wordpress.com/rest/v1.1/tracks/record';

	/**
	 * @var WP_Http
	 */
	private WP_Http $http;

	/**
	 * Constructor.
	 */
	public function __construct( WP_Http $http = null ) {
		$this->http = $http ?? _wp_http_get_object();
	}

	/**
	 * Record a batch of events using the Tracks REST API
	 * 
	 * @param Tracks_Event[] $events Array of Tracks_Event objects to record
	 * @return bool|WP_Error True if batch recording succeeded.
	 *                       WP_Error is any error occured.
	 */
	public function batch_record_events( array $events, array $common_props = [] ): bool|WP_Error {
		// filter out invalid events
		$valid_events = array_filter( $events, function ( $event ) {
			return $event instanceof Tracks_Event && $event->is_recordable() === true;
		} );

		// no events - nothing to do
		if ( [] === $valid_events ) {
			return true;
		}

		$body = [
			'events'      => $valid_events,
			'commonProps' => $common_props,
		];

		$response = $this->http->post(
			static::TRACKS_ENDPOINT,
			array(
				'body'       => wp_json_encode( $body ),
				'user-agent' => 'viptelemetry',
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => 'error batch recording events to Tracks',
				'extra'    => [
					'error' => $response->get_error_messages(),
				],
			] );
			return $response;
		}

		return true;
	}
}
