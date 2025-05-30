<?php
/**
 * Telemetry: Pendo Track Client class
 *
 * @package Automattic\VIP\Telemetry\Pendo
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use WP_Error;
use WP_Http;
use Automattic\VIP\Telemetry\Telemetry_Client;
use function Automattic\VIP\Logstash\log2logstash;

/**
 * Handles sending Track events to the Pendo API.
 */
class Pendo_Track_Client extends Telemetry_Client {
	/**
	 * Pendo API endpoint for "Track events"
	 *
	 * https://support.pendo.io/hc/en-us/articles/360032294151-Track-Events
	 */
	protected const PENDO_ENDPOINT = 'https://app.pendo.io/data/track';

	/**
	 * @var string
	 */
	private string|null $api_key;

	/**
	 * @var WP_Http
	 */
	private WP_Http $http;

	/**
	 * Constructor.
	 */
	public function __construct( string|null $api_key = null, ?WP_Http $http = null ) {
		$this->api_key = $api_key;
		$this->http    = $http ?? _wp_http_get_object();
	}

	/**
	 * Record a batch of Track events using the Pendo API
	 *
	 * @param Pendo_Track_Event[] $events Array of Pendo_Track_Event objects to record
	 * @return bool|WP_Error True on success or WP_Error if any error occured.
	 */
	public function batch_record_events( array $events, array $common_props = [] ): bool|WP_Error {
		if ( empty( $this->api_key ) ) {
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => 'Pendo Track integration key is not defined',
			] );
			return new WP_Error( 'pendo_track_integration_key_not_defined', 'Pendo Track integration key is not defined' );
		}

		// Filter out invalid events.
		$valid_events = array_filter( $events, function ( $event ) {
			return $event instanceof Pendo_Track_Event && $event->is_recordable() === true;
		} );

		// No events? Nothing to do.
		if ( empty( $valid_events ) ) {
			return true;
		}

		// Pendo's API does not accept bulk Track events, so we need to send them one
		// at a time. :/
		foreach ( $valid_events as $body ) {
			$response = $this->http->post(
				self::PENDO_ENDPOINT,
				[
					'body'       => wp_json_encode( $body ),
					'user-agent' => 'viptelemetry',
					'headers'    => array(
						'Content-Type'            => 'application/json',
						'x-pendo-integration-key' => $this->api_key,
					),
				]
			);

			// Short-circuit on the first error.
			if ( is_wp_error( $response ) ) {
				log2logstash( [
					'severity' => 'error',
					'feature'  => 'telemetry',
					'message'  => 'Error recording events to Pendo',
					'extra'    => [
						'error' => $response->get_error_messages(),
					],
				] );

				return $response;
			}
		}

		return true;
	}
}
