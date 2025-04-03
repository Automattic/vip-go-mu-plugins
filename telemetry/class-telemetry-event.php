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
	 * Variable containing the event's data or a WP_Error if an error was
	 * encountered during the event's creation or validation.
	 *
	 * @var object|WP_Error
	 */
	protected object $data;

	/**
	 * Generate the event data.
	 *
	 * @return object|WP_Error A serializable object if the event is valid, otherwise a WP_Error
	 */
	abstract protected function generate(): object;

	/**
	 * Wraps generate() and stores the result to prevent multiple calls.
	 *
	 * @return object|WP_Error A serializable object if the event is valid, otherwise a WP_Error
	 */
	public function get_data(): object {
		if ( ! isset( $this->data ) ) {
			$this->data = $this->generate();
		}

		return $this->data;
	}

	/**
	 * Returns whether the event can be recorded.
	 *
	 * @return bool|WP_Error True if the event is recordable.
	 */
	public function is_recordable(): bool|WP_Error {
		$data = $this->get_data();

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return true;
	}

	/**
	 * Returns the event's data for JSON representation.
	 */
	public function jsonSerialize(): mixed {
		$data = $this->get_data();

		if ( is_wp_error( $data ) ) {
			return (object) [];
		}

		return $data;
	}
}
