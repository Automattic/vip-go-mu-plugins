<?php
/**
 * Telemetry: event queue.
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * Handles queued events to be sent to a telemetry service.
 */
class Telemetry_Event_Queue {

	/**
	 * @var Telemetry_Client The client to use to record events.
	 */
	private Telemetry_Client $client;

	/**
	 * Events queued to be sent to the telemetry service.
	 *
	 * @var array<Telemetry_Event>
	 */
	protected array $events = [];

	/**
	 * Constructor. Registers the shutdown hook to record any and all events.
	 */
	public function __construct( Telemetry_Client $client ) {
		$this->client = $client;

		// Register the shutdown hook to record any and all events
		add_action( 'shutdown', array( $this, 'record_events' ) );
	}

	/**
	 * Enqueues an event to be recorded asynchronously.
	 *
	 * @param Telemetry_Event $event The event to record.
	 * @return bool|WP_Error True if the event was enqueued for recording.
	 *                       False if the event is not recordable.
	 *                       WP_Error if the event is generating an error.
	 */
	public function record_event_asynchronously( Telemetry_Event $event ): bool|WP_Error {
		$is_event_recordable = $event->is_recordable();

		if ( true !== $is_event_recordable ) {
			return $is_event_recordable;
		}

		$this->events[] = $event;

		return true;
	}

	/**
	 * Records all queued events synchronously.
	 */
	public function record_events(): void {
		if ( [] === $this->events ) {
			return;
		}

		// No back-off mechanism is implemented here, given the low cost of missing a few events.
		// We also need to ensure that there's minimal disruption to a site's operations.
		$this->client->batch_record_events( $this->events );
		$this->events = [];
	}
}
