<?php
/**
 * File which contain common utils related to Parse.ly.
 *
 * @package Automattic\Test\Utils
 */

namespace Automattic\Test\Utils;

/**
 * Get test mode of parsely.
 */
function get_parsely_test_mode(): string {
	$mode = getenv( 'WPVIP_PARSELY_INTEGRATION_TEST_MODE' );
	return $mode ?: 'disabled';
}

/**
 * Check if parsely is disabled.
 */
function is_parsely_disabled(): bool {
	return in_array( get_parsely_test_mode(), [ 'disabled', 'filter_disabled', 'option_disabled', 'filter_and_option_disabled' ] );
}
