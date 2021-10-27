<?php
/**
 * Tracks class
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_Error;

/**
 * This class comprises the mechanics of sending events to the Automattic Tracks system.
 */
class Tracks implements Telemetry_System {
	/**
	 * List of events to send to the tracks event on flush.
	 *
	 * @var array
	 */
	private $queue = array();

	const EVENT_NAME_PREFIX = 'wpparsely_';
	const TRACKS_RECORD_URL = 'https://public-api.wordpress.com/rest/v1.1/tracks/record';

	/**
	 * Sets up a "shutdown function" to call `flush_queue`.
	 * This is to send events as one of the last thing the backend does to serve a request.
	 *
	 * @return void
	 */
	public function setup(): void {
		register_shutdown_function( array( $this, 'flush_queue' ) );
	}

	/**
	 * Send the contents of the queue (if any) to the API.
	 * This is hooked into a shutdown function via `setup`, but can be called any time if desired.
	 *
	 * @return void
	 */
	public function flush_queue(): void {
		if ( count( $this->queue ) === 0 ) {
			return;
		}
		self::send_events_to_api( $this->queue );
	}

	/**
	 * Record an event to the Automattic Tracks API.
	 *
	 * NOTE: If the event name and / or property names don't pass validation, they'll be silently discarded.
	 *
	 * @param string $event_name The event name. Must be snake_case.
	 * @param array  $event_props Any additional properties to include with the event. Key names must be valid (start with a lower-case letter and "snake case").
	 * @param bool   $send_immediately Should the event be sent to the backend immediately? Default: false.
	 * @return bool|WP_Error True if the event could be enqueued or send correctly. WP_Error otherwise
	 */
	public function record_event( string $event_name, array $event_props = array(), bool $send_immediately = false ) {
		$event_object = self::normalize_event( $event_name, $event_props );
		$event        = $event_object->data;
		if ( is_wp_error( $event ) ) {
			return $event;
		}

		if ( $send_immediately ) {
			$response = self::send_events_to_api( array( $event ) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( ! is_int( $status_code ) || $status_code >= 300 || $status_code < 200 ) {
				return new WP_Error( 'request_error', 'The request to the tracks service was invalid', $status_code );
			}

			return true;
		}

		$this->queue[] = $event;
		return true;
	}

	/**
	 * Convert input about the event to a conventional format.
	 *
	 * @param string $event_name The (potentially-unprefixed) event name.
	 * @param array  $event_props Any additional properties to include with the event.
	 *
	 * @return Tracks_Event The normalized event materialized as a Tracks_Event object
	 */
	private static function normalize_event( string $event_name, array $event_props = array() ): Tracks_Event {
		$_event_props = array();
		foreach ( $event_props as $key => $value ) {
			if ( is_string( $value ) ) {
				$_event_props[ $key ] = $value;
				continue;
			}
			$_event_props[ $key ] = wp_json_encode( $value );
		}

		$event = array_merge(
			$_event_props,
			array(
				'_en' => self::normalize_event_name( $event_name ),
			)
		);

		return new Tracks_Event( $event );
	}

	/**
	 * Convert input about the event name to a conventional format.
	 * This is mainly to ensure all events have our prefix
	 *
	 * @param string $event_name The provided event name that may (or may not be) in the desired format.
	 * @return string The event name in the conventional format.
	 */
	private static function normalize_event_name( string $event_name ): string {
		return preg_replace( '/^(?:' . self::EVENT_NAME_PREFIX . ')?(.*)/', self::EVENT_NAME_PREFIX . '\1', $event_name );
	}

	/**
	 * Send passed events to the WordPress.com API for recording.
	 *
	 * @param array $events A list of Parsely_A8c_Tracks_Event objects.
	 * @param array $common_props Any properties that should be included in all events in this batch.
	 * @param bool  $blocking Passed to `wp_remote_post`. Default: true.
	 *
	 * @see https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters
	 * @return array|WP_Error The response or WP_Error on failure.
	 */
	private static function send_events_to_api( array $events, array $common_props = array(), bool $blocking = true ) {
		return wp_remote_post(
			self::TRACKS_RECORD_URL,
			array(
				'blocking' => $blocking,
				'body'     => array(
					'events'      => $events,
					'commonProps' => $common_props,
				),
			)
		);
	}
}
