<?php
/**
 * Telemetry: Telemetry class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\VIP\Telemetry\Pendo;
use Automattic\VIP\Telemetry\Tracks;
use WP_Error;

/**
 * This class comprises the mechanics of sending events to all configured
 * telemetry systems.
 */
class Telemetry extends Telemetry_System {

	/**
	 * Configured telemetry systems.
	 *
	 * @var array<Telemetry_Systems>
	 */
	private array $systems = [];

	/**
	 * Telemetry constructor.
	 *
	 * @param string|null $event_prefix The prefix for all event names, or null to use the default prefix.
	 * @param array<string, mixed>|null $global_event_properties The global event properties to be included with every event.
	 * @param Telemetry_Event_Queue|null $queue The event queue to use. Falls back to the default queue when none provided.
	 */
	public function __construct( ?string $event_prefix = null, ?array $global_event_properties = [], ?Telemetry_Event_Queue $queue = null ) {
		$this->systems = [
			new Pendo( $event_prefix, $global_event_properties, $queue ),
			new Tracks( $event_prefix, $global_event_properties, $queue ),
		];
	}

	/**
	 * Records an event to the configured telemetry systems.
	 *
	 * If the event doesn't pass validation, it gets silently discarded.
	 *
	 * @param string                            $event_name The event name. Must be snake_case.
	 * @param array<string, mixed>|array<empty> $event_properties Any additional properties to include with the event.
	 *                                                            Key names must be lowercase and snake_case.
	 * @return bool|WP_Error True if recording all events succeeded.
	 *                       False if any of the telemetry systems are disabled.
	 *                       WP_Error if any event recording failed.
	 */
	public function record_event(
		string $event_name,
		array $event_properties = []
	): bool|WP_Error {
		$return_value = true;

		foreach ( $this->systems as $system ) {
			$result = $system->record_event( $event_name, $event_properties );

			// If any system fails to record the event, return the error.
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$return_value = $return_value && $result;
		}

		return $return_value;
	}
}
