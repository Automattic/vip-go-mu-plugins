<?php
/**
 * Telemetry: Telemetry client abstract class
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_Error;
use function Automattic\VIP\Logstash\log2logstash;

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
	abstract public function batch_record_events( array $events, array $common_props = [] ): bool|WP_Error;

	/**
	 * Log selected HTTP failure details without including arbitrary response data.
	 *
	 * @param WP_Error   $error Request or acknowledgement error.
	 * @param int|string $status_code HTTP status, or an empty string for transport failures.
	 * @param string     $message Provider-specific log message.
	 */
	protected function log_recording_error( WP_Error $error, int|string $status_code, string $message ): void {
		log2logstash( [
			'severity' => 'error',
			'feature'  => 'telemetry',
			'message'  => $message,
			'extra'    => [
				'error'       => $error->get_error_messages(),
				'error_codes' => $error->get_error_codes(),
				'http_status' => $status_code ?: null,
			],
		] );
	}
}
