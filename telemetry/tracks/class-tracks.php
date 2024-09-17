<?php
/**
 * Telemetry: Tracks class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * This class comprises the mechanics of sending events to the Automattic Tracks
 * system.
 */
class Tracks extends Telemetry_System {

	/**
	 * The prefix for all event names.
	 *
	 * @var string
	 */
	protected $event_prefix;

	/**
	 * Client instance for testing purposes.
	 *
	 * @var Tracks_Client
	 */
	private $client;

	/**
	 * Tracks constructor.
	 * 
	 * @param string $event_prefix The prefix for all event names. Defaults to 'vip_'.
	 * @param Tracks_Client|null $client The client instance to use. Falls back to the default client when none provided.
	 */
	public function __construct( string $event_prefix = 'vip_', Tracks_Client $client = null ) {
		$this->event_prefix = $event_prefix;
		$this->client       = $client ?? Tracks_Client::instance();
	}

	/**
	 * Records an event to Tracks by using the Tracks pixel.
	 *
	 * Depending on the current context, the pixel will be recorded
	 * synchronously (as a GET request) or as asynchronously (as an injected
	 * pixel into the page's footer).
	 *
	 * If the event doesn't pass validation, it gets silently discarded.
	 *
	 * @param string                            $event_name The event name. Must be snake_case.
	 * @param array<string, mixed>|array<empty> $event_properties Any additional properties to include with the event.
	 *                                                            Key names must be lowercase and snake_case.
	 * @return bool|WP_Error True if recording the event succeeded.
	 *                       False if telemetry is disabled.
	 *                       WP_Error if recording the event failed.
	 */
	public function record_event(
		string $event_name,
		array $event_properties = array()
	) {
		$event = new Tracks_Event( $this->event_prefix, $event_name, $event_properties );

		return $this->client->record_event_asynchronously( $event );
	}
}
