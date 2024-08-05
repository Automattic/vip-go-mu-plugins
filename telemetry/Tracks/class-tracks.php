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
	 * Registers the events into WordPress hooks to activate tracking.
	 */
	public function run(): void {
		$this->activate_tracking();
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
		$event = new Tracks_Event( $event_name, $event_properties );
		$pixel = Tracks_Client::instance();

		// Process AJAX/REST request events immediately.
		if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
			$pixel->record_event_synchronously( $event );
		}

		return $pixel->record_event_asynchronously( $event );
	}

	/**
	 * Registers the events into their respective WordPress hooks, so they
	 * can be recorded when the hook fires.
	 */
	protected function activate_tracking(): void {
		foreach ( $this->events as $event ) {
			if ( is_string( $event['action_hook'] ) && is_callable( $event['callable'] ) ) {
				$accepted_args = $event['accepted_args'] ?? 1;
				$func          = function () use ( $accepted_args, $event ) {
					if ( $accepted_args > 1 ) {
						$args   = func_get_args();
						$args[] = $this;
					} else {
						$args = array( $this );
					}
					return call_user_func_array( $event['callable'], $args );
				};

				add_filter( $event['action_hook'], $func, 10, (int) $accepted_args );
			}
		}
	}
}
