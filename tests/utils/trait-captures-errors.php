<?php
/**
 * Helper for asserting on errors raised with trigger_error().
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

trait Captures_Errors {
	/**
	 * Run a callable and capture the errors it raises instead of letting them reach PHPUnit.
	 *
	 * Lets one test assert both the return value and the error message. Errors excluded from
	 * error_reporting() (for example, suppressed with `@`) are not captured.
	 *
	 * @param callable $fn     The code under test.
	 * @param int      $levels Error levels to capture.
	 *
	 * @return array{0: mixed, 1: string[]} The callable's return value and the captured error messages.
	 */
	protected function capture_errors( callable $fn, int $levels = E_USER_WARNING ): array {
		$messages = [];

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ) use ( &$messages ): bool {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			if ( ! ( error_reporting() & $errno ) ) {
				return false;
			}

			$messages[] = $errstr;
			return true;
		}, $levels );

		try {
			$result = $fn();
		} finally {
			restore_error_handler();
		}

		return [ $result, $messages ];
	}
}
