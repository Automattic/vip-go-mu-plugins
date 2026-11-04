<?php
/**
 * Telemetry: Pendo class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;

/**
 * Pendo has been removed, and this class does nothing. It is kept for one
 * release because deploys are non-atomic: the previous release's
 * `class-telemetry.php` and bootstrap still reference it, and plugins call
 * `Pendo::enable_javascript_library()` when the class exists.
 *
 * @deprecated Delete with the rest of `telemetry/pendo/` once this release has fully rolled out.
 */
class Pendo extends Telemetry_System {
	/**
	 * Accepts and ignores the previous constructor arguments.
	 *
	 * @param mixed ...$args Ignored.
	 */
	public function __construct( ...$args ) {
	}

	/**
	 * Does nothing.
	 */
	public static function enable_javascript_library(): void {
	}

	/**
	 * Records nothing.
	 *
	 * @param string               $event_name       Ignored.
	 * @param array<string, mixed> $event_properties Ignored.
	 * @return bool|WP_Error Always false, as when Pendo was disabled.
	 */
	public function record_event( string $event_name, array $event_properties = [] ): bool|WP_Error {
		return false;
	}
}
