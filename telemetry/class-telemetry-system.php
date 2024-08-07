<?php
/**
 * Telemetry: Telemetry System abstract class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * Base class for all telemetry system implementations.
 */
abstract class Telemetry_System {
	/**
	 * Holds the list of events to be tracked.
	 *
	 * @var array<array<string, string|int>>
	 */
	protected $events;

	/**
	 * Registers the telemetry system.
	 */
	abstract public function run(): void;

	/**
	 * Activates event tracking.
	 */
	abstract protected function activate_tracking(): void;

	/**
	 * Registers the passed events so they can be recorded later.
	 *
	 * Note: All events must be registered before the run() function of this
	 * class gets called.
	 *
	 * @param array<string, string|int> ...$events The events to register.
	 */
	public function register_events( array ...$events ): void {
		foreach ( $events as $event ) {
			$this->events[] = $event;
		}
	}

	/**
	 * Records the passed event.
	 *
	 * @param string               $event_name The event's name.
	 * @param array<string, mixed> $event_properties Any additional properties
	 *                                               to include with the event.
	 * @return bool|WP_Error True if recording the event succeeded.
	 *                       False if telemetry is disabled.
	 *                       WP_Error if recording the event failed.
	 */
	abstract public function record_event(
		string $event_name,
		array $event_properties = array()
	);
}
