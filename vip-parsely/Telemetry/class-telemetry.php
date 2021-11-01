<?php
/**
 * Telemetry class
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * This class comprises the mechanics of setting up the back end tracking instance(s).
 * Currently, the only supported back end is Automattic Tracks.
 * This is intended to wrap the internals such that adding / changing back ends has minimal impact on the event hooks we're interested in.
 */
class Telemetry {
	/**
	 * Holds an instance of the class comprising the active telemetry system.
	 *
	 * @var Telemetry_System
	 */
	private $telemetry_system;

	/**
	 * Holds the list of events that are registered to WordPress hooks.
	 *
	 * @var array
	 */
	private $events;

	/**
	 * Parsely_Telemetry constructor.
	 */
	public function __construct( Telemetry_System $telemetry_system ) {
		$this->telemetry_system = $telemetry_system;
	}

	/**
	 *  Initializes the telemetry system and registers the events into WordPress hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->telemetry_system->setup();
		$this->add_event_tracking();
	}

	/**
	 * Adds an event to the list of supported events. In order to have it registered, it must be
	 * added before calling `run`.
	 *
	 * @param array $event
	 * @return void
	 */
	public function register_event( array $event ): void {
		$this->events[] = $event;
	}

	/**
	 * Hook functions into WordPress actions and / filters for which we want to record events.
	 *
	 * @return void
	 */
	private function add_event_tracking(): void {
		foreach ( $this->events as $event ) {
			if ( is_string( $event['action_hook'] ) && is_callable( $event['callable'] ) ) {
				$accepted_args = $event['accepted_args'] ?? 1;
				$func          = function() use ( $accepted_args, $event ) {
					if ( $accepted_args > 1 ) {
						$args   = func_get_args();
						$args[] = $this->telemetry_system;
					} else {
						$args = array( $this->telemetry_system );
					}
					return call_user_func_array( $event['callable'], $args );
				};
				add_filter( $event['action_hook'], $func, 10, $accepted_args );
			}
		}
	}
}
