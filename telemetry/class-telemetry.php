<?php
/**
 * Telemetry: Telemetry class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

/**
 * The default entry point for recording telemetry events. Events are sent to
 * Tracks.
 */
class Telemetry extends Tracks {
	/**
	 * Telemetry constructor.
	 *
	 * @param string|null $event_prefix The prefix for all event names, or null to use the default prefix.
	 * @param array<string, mixed>|null $global_event_properties The global event properties to be included with every event.
	 * @param Telemetry_Event_Queue|null $queue The event queue to use. Falls back to the default queue when none provided.
	 */
	public function __construct( ?string $event_prefix = null, ?array $global_event_properties = [], ?Telemetry_Event_Queue $queue = null ) {
		parent::__construct( $event_prefix ?? 'vip_', $global_event_properties ?? [], $queue );
	}
}
