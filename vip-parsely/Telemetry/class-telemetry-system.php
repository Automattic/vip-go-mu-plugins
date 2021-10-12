<?php
/**
 * Telemetry System interface
 *
 * @package Parsely\Telemetry
 * @since 3.0.0
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * Telemetry back-end classes are expected to conform this interface.
 *
 * @since 3.0.0
 */
interface Telemetry_System {
	/**
	 * Sets up a "shutdown function" to call `flush_queue`.
	 * This is to send events as one of the last thing the backend does to serve a request.
	 *
	 * @return void
	 */
	public function setup(): void;

	/**
	 * Send the contents of the queue (if any) to the API.
	 * This is hooked into a shutdown function via `setup`, but can be called any time if desired.
	 *
	 * @return void
	 */
	public function flush_queue(): void;

	/**
	 * Record an event to the Automattic Tracks API.
	 *
	 * NOTE: If the event name and / or property names don't pass validation, they'll be silently discarded.
	 *
	 * @param string $event_name The event name. Must be snake_case.
	 * @param array  $event_props Any additional properties to include with the event. Key names must be valid (start with a lower-case letter and "snake case").
	 * @param bool   $blocking Should the event be sent to the backend immediately? Default: false.
	 * @return void
	 */
	public function record_event( string $event_name, array $event_props = array(), bool $blocking = false): void;
}
