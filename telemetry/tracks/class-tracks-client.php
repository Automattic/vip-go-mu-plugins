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
	 * Base URL of the Tracks pixel.
	 */
	protected const PIXEL_BASE_URL = 'https://pixel.wp.com/t.gif';

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
	 * @var array<Tracks_Event>
	 */
	protected $events = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Track events asynchronously (inject pixels in the footer).
		add_action( 'admin_footer', array( $this, 'render_tracking_pixels' ) );
		// Synchronously track any remaining events that could not be added to
		// the footer.
		add_action( 'shutdown', array( $this, 'record_remaining_events' ) );
	}

	/**
	 * Instantiates the singleton.
	 *
	 * @return Tracks_Client
	 */
	public static function instance(): Tracks_Client {
		if ( is_null( static::$instance ) ) {
			static::$instance = new Tracks_Client();
		}

		return static::$instance;
	}

	/**
	 * Enqueues an event to be recorded asynchronously.
	 *
	 * @param Tracks_Event $event The event to record.
	 * @return bool|WP_Error True if the event was enqueued for recording.
	 *                       False if the event is not recordable.
	 *                       WP_Error if the event is generating an error.
	 */
	public function record_event_asynchronously( Tracks_Event $event ) {
		$is_event_recordable = $event->is_recordable();

		if ( true !== $is_event_recordable ) {
			return $is_event_recordable;
		}

		static::instance()->events[] = $event;

		return true;
	}

	/**
	 * Records an event synchronously (using a GET request).
	 *
	 * @param Tracks_Event $event The event to record.
	 * @return bool|WP_Error True if recording the event succeeded.
	 *                       False if the event is not recordable.
	 *                       WP_Error if any error occurred.
	 */
	public function record_event_synchronously( Tracks_Event $event ) {
		$is_event_recordable = $event->is_recordable();

		if ( true !== $is_event_recordable ) {
			return $is_event_recordable;
		}

		$pixel_url = static::instance()->generate_pixel_url( $event );

		if ( null === $pixel_url ) {
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => 'cannot generate tracks pixel for given input',
				'extra'    => [
					'event' => (array) $event,
				],
			] );
			return new WP_Error(
				'invalid_pixel',
				'cannot generate tracks pixel for given input',
				array( 'status' => 400 )
			);
		}

		// Add the Request Timestamp and URL terminator just before the HTTP
		// request.
		$pixel_url .= '&_rt=' . static::build_timestamp() . '&_=_';

		$request = wp_safe_remote_get(
			$pixel_url,
			array(
				'user-agent'  => 'viptelemetry',
				'blocking'    => false,
				'redirection' => 2,
				'httpversion' => '1.1',
				'timeout'     => 1,
			)
		);

		if ( is_wp_error( $request ) ) {
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => 'error recording event to Tracks',
				'extra'    => [
					'error' => $request->get_error_messages(),
					'event' => (array) $event,
				],
			] );
			return $request;
		}

		return true;
	}

	/**
	 * Record a batch of events using the Tracks REST API
	 * 
	 * @param Track_Event[] $events Array of Tracks_Event objects to record
	 * @return bool|WP_Error True if batch recording succeeded.
	 *                       WP_Error is any error occured.
	 */
	public function batch_record_events( array $events, array $common_props = [] ) {
		// filter out invalid events
		$filtered_events = array_filter( $events, function ( $event ) {
			return $event instanceof Tracks_Event && $event->is_recordable();
		} );

		// convert array of Tracks_Event objects to associative arrays
		$event_data = array_map( function ( $event ) {
			return (array) $event->get_data();
		}, $filtered_events );

		$body = [
			'events'      => $event_data,
			'commonProps' => $common_props,
		];

		$request = wp_remote_post(
			static::TRACKS_ENDPOINT,
			array(
				'body'       => wp_json_encode( $body ),
				'user-agent' => 'viptelemetry',
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $request ) ) {
			log2logstash( [
				'severity' => 'error',
				'feature'  => 'telemetry',
				'message'  => 'error batch recording events to Tracks',
				'extra'    => [
					'error' => $request->get_error_messages(),
				],
			] );
			return $request;
		}

		return true;
	}

	/**
	 * Outputs a Tracks pixel for every registered event.
	 */
	public function render_tracking_pixels(): void {
		foreach ( $this->events as $event ) {
			$pixel_url = $this->generate_pixel_url( $event );

			if ( null === $pixel_url ) {
				continue;
			}

			echo '<img style="position: fixed;" src="', esc_url( $pixel_url ), '" />';
		}

		$this->events = array();
	}

	/**
	 * Generates a Tracks pixel URL that will record an event when accessed.
	 *
	 * @param Tracks_Event $event The event for which to generate the pixel URL.
	 * @return ?string The pixel URL or null if an error occurred.
	 */
	protected function generate_pixel_url( Tracks_Event $event ): ?string {
		$event_data = $event->get_data();

		if ( is_wp_error( $event_data ) ) {
			return null;
		}

		$args = get_object_vars( $event_data );

		// Request Timestamp and URL Terminator must be added just before the
		// HTTP request or not at all.
		unset( $args['_rt'], $args['_'] );

		return esc_url_raw(
			static::PIXEL_BASE_URL . '?' . http_build_query( $args )
		);
	}

	/**
	 * Records any remaining events synchronously.
	 */
	public function record_remaining_events(): void {
		static::instance()->batch_record_events( $this->events );
	}

	/**
	 * Create a timestamp representing milliseconds since 1970-01-01
	 *
	 * @return string A string representing a timestamp.
	 */
	public static function build_timestamp() {
		$ts = round( microtime( true ) * 1000 );

		return number_format( $ts, 0, '', '' );
	}
}
