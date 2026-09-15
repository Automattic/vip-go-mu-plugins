<?php
/**
 * Telemetry: Abilities API invocation tracking (internal-only)
 *
 * Records how often registered WP Abilities are actually invoked, and whether
 * the call succeeded. Gated to a handful of environments via the
 * 'telemetry-internal-only' feature flag while we evaluate the performance
 * impact of measuring this at runtime, per
 * https://vipproductp2.wordpress.com/2026/07/24/should-we-start-collecting-core-usage-telemetry/
 *
 * @package Automattic\VIP\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Abilities;

use Automattic\VIP\Feature;
use Automattic\VIP\Telemetry\Tracks;

// Limit to production, non-sandboxed environments, same as the rest of stats.php,
// and further gate to the internal-only telemetry environment list so we can
// measure the cost of this before any wider rollout.
if (
	true === WPCOM_IS_VIP_ENV
	&& false === WPCOM_SANDBOXED
	&& true === Feature::is_enabled_by_ids( 'telemetry-internal-only' )
) {
	// PHP_INT_MAX to run after any other filter (e.g. the WordPress MCP integration's
	// wp_register_ability_args handler) that might replace execute_callback outright.
	add_filter( 'wp_register_ability_args', __NAMESPACE__ . '\track_ability_execution', PHP_INT_MAX, 2 );
}

/**
 * Wraps an ability's execute_callback so each invocation is recorded via telemetry.
 *
 * @param array<string, mixed> $args Ability registration args.
 * @param string $ability_name Ability name, e.g. 'my-plugin/my-ability'.
 * @return array<string, mixed> Filtered ability registration args.
 */
function track_ability_execution( array $args, string $ability_name ): array {
	if ( ! isset( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
		return $args;
	}

	$original_execute_callback = $args['execute_callback'];

	$args['execute_callback'] = function ( ...$callback_args ) use ( $original_execute_callback, $ability_name ) {
		$result = call_user_func_array( $original_execute_callback, $callback_args );

		record_invocation( $ability_name, ! is_wp_error( $result ) );

		return $result;
	};

	return $args;
}

/**
 * Records a single ability invocation event.
 *
 * @param string $ability_name The invoked ability's name.
 * @param bool $success Whether the execute_callback returned a non-error result.
 */
function record_invocation( string $ability_name, bool $success ): void {
	static $tracks = null;
	if ( null === $tracks ) {
		$tracks = new Tracks();
	}

	$tracks->record_event( 'abilities_api_invoked', [
		'ability_name' => $ability_name,
		'success'      => $success,
	] );
}
