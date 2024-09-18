<?php
/**
 * Telemetry: Tracks class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\VIP\Telemetry\Tracks\Tracks_Client;
use Automattic\VIP\Telemetry\Tracks\Tracks_Event;
use Automattic\VIP\Telemetry\Tracks\Tracks_Event_Queue;
use WP_Error;

/**
 * This class comprises the mechanics of sending events to the Automattic
 * Tracks system.
 */
class Tracks extends Telemetry_System {

	/**
	 * The prefix for all event names.
	 *
	 * @var string
	 */
	protected $event_prefix;

	/**
	 * Event queue.
	 *
	 * @var Tracks_Event_Queue
	 */
	private $queue;

	/**
	 * @param array<string, mixed> The global event properties to be included with every event.
	 */
	private array $global_event_properties = array();

	/**
	 * Tracks constructor.
	 * 
	 * @param string $event_prefix The prefix for all event names. Defaults to 'vip_'.
	 * @param array<string, mixed> $global_event_properties The global event properties to be included with every event.
	 * @param Tracks_Event_Queue|null $queue The event queue to use. Falls back to the default queue when none provided.
	 * @param Tracks_Client|null $client The client instance to use. Falls back to the default client when none provided.
	 */
	public function __construct( string $event_prefix = 'vip_', array $global_event_properties = [], Tracks_Event_Queue $queue = null, Tracks_Client $client = null ) {
		$this->event_prefix            = $event_prefix;
		$this->global_event_properties = $global_event_properties;
		$client                      ??= new Tracks_Client();
		$this->queue                   = $queue ?? new Tracks_Event_Queue( $client );
	}

	/**
	 * Records an event to Tracks by using the Tracks API.
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
		if ( [] !== $this->global_event_properties ) {
			$event_properties = array_merge( $this->global_event_properties, $event_properties );
		}

		$event = new Tracks_Event( $this->event_prefix, $event_name, $event_properties );

		return $this->queue->record_event_asynchronously( $event );
	}
}
