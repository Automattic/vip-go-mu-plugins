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
	): bool|WP_Error;
}
