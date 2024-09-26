<?php
/**
 * Telemetry: Telemetry client abstract class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use JsonSerializable;
use WP_Error;

/**
 * Base class for all telemetry event implementations.
 */
abstract class Telemetry_Event implements JsonSerializable {

	/**
	 * Returns whether the event can be recorded.
	 *
	 * @return bool|WP_Error True if the event is recordable.
	 *                        WP_Error is any error occurred.
	 */
	abstract public function is_recordable();
}
