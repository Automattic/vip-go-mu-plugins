<?php
/**
 * Telemetry: Telemetry class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * The default entry point for recording telemetry events. Events are sent to
 * Tracks.
 *
 * This wraps Tracks instead of extending it, because the previous release's
 * bootstrap loads this file before the Tracks class during a non-atomic deploy.
 */
class Telemetry extends Telemetry_System {
	/**
	 * The Tracks system that records events.
	 *
	 * @var Tracks
	 */
	private Tracks $tracks;

	/**
	 * Telemetry constructor.
	 *
	 * @param string|null $event_prefix The prefix for all event names, or null to use the default prefix.
	 * @param array<string, mixed>|null $global_event_properties The global event properties to be included with every event.
	 * @param Telemetry_Event_Queue|null $queue The event queue to use. Falls back to the default queue when none provided.
	 */
	public function __construct( ?string $event_prefix = null, ?array $global_event_properties = [], ?Telemetry_Event_Queue $queue = null ) {
		$this->tracks = new Tracks( $event_prefix ?? 'vip_', $global_event_properties ?? [], $queue );
	}

	/**
	 * Records an event to Tracks.
	 *
	 * @param string                            $event_name The event name. Must be snake_case.
	 * @param array<string, mixed>|array<empty> $event_properties Any additional properties to include with the event.
	 * @return bool|WP_Error True if the event was queued, WP_Error if it failed validation.
	 */
	public function record_event( string $event_name, array $event_properties = [] ): bool|WP_Error {
		return $this->tracks->record_event( $event_name, $event_properties );
	}
}
