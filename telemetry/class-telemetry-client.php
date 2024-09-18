<?php
/**
 * Telemetry: Telemetry client abstract class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * Base class for all telemetry client implementations.
 */
abstract class Telemetry_Client {
	/**
	 * Record a batch of events using the telemetry API
	 *
	 * @param Telemetry_Event[] $events Array of Tracks_Event objects to record
	 * @return bool|WP_Error True if batch recording succeeded.
	 *                       WP_Error is any error occurred.
	 */
	abstract public function batch_record_events( array $events, array $common_props = [] );
}
