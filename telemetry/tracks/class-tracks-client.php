<?php
/**
 * Telemetry: Tracks Pixel class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;
use function Automattic\VIP\Logstash\log2logstash;

/**
 * Handles all operations related to the Tracks pixel.
 */
class Tracks_Client {

	/**
	 * Tracks REST API endpoint for post requests
	 */
	protected const TRACKS_ENDPOINT = 'https://public-api.wordpress.com/rest/v1.1/tracks/record';

	/**
	 * Class singleton instance.
	 *
	 * @var ?Tracks_Client
	 */
	protected static $instance = null;

	/**
	 * Events queued to be sent to the Tracks pixel.
	 *
	 * @var array<Tracks_Event_Builder>
	 */
	protected $events = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register the shutdown hook to record any and all events
		add_action( 'shutdown', array( $this, 'record_events' ) );
	}

	/**
	 * Instantiates the singleton.
	 *
	 * @return Tracks_Client
	 */
	public static function instance(): Tracks_Client {
		if ( null === static::$instance ) {
			static::$instance = new Tracks_Client();
		}

		return static::$instance;
	}

	/**
	 * Enqueues an event to be recorded asynchronously.
	 *
	 * @param Tracks_Event_Builder $event The event to record.
	 * @return bool|WP_Error True if the event was enqueued for recording.
	 *                       False if the event is not recordable.
	 *                       WP_Error if the event is generating an error.
	 */
	public function record_event_asynchronously( Tracks_Event_Builder $event ) {
		$is_event_recordable = $event->is_recordable();

		if ( true !== $is_event_recordable ) {
			return $is_event_recordable;
		}

		static::instance()->events[] = $event;

		return true;
	}

	/**
	 * Record a batch of events using the Tracks REST API
	 * 
	 * @param Tracks_Event_Builder[] $events Array of Tracks_Event_Builder objects to record
	 * @return bool|WP_Error True if batch recording succeeded.
	 *                       WP_Error is any error occured.
	 */
	public function batch_record_events( array $events, array $common_props = [] ) {
		// filter out invalid events
		$valid_events = array_filter( $events, function ( $event ) {
			return $event instanceof Tracks_Event_Builder && $event->is_recordable() === true;
		} );

		$body = [
			'events'      => $valid_events,
			'commonProps' => $common_props,
		];

		$response = wp_remote_post(
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

	/**
	 * Records any remaining events synchronously.
	 */
	public function record_events(): void {
		static::instance()->batch_record_events( $this->events );
	}
}
