<?php
/**
 * Tracking code for the `update_option_parsely` event (whenever the Parse.ly option, usually in the Parse.ly
 * settings page, is updated).
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

/**
 * Records an event using the given Telemetry System when the Parse.ly option is updated. It will only send the event in
 * case some values in the option are updated. If that's the case, those changed values will also be sent in the event.
 *
 * @param array $old_value  The old option value.
 * @param array $value      The new option value.
 * @param Telemetry_System $telemetry_system
 * @return void
 */
function track_option_updated( array $old_value, array $value, Telemetry_System $telemetry_system ): void {
	$all_keys = array_unique( array_merge( array_keys( $old_value ), array_keys( $value ) ) );

	// Get the option keys that got updated.
	$updated_keys = array_reduce(
		$all_keys,
		function( $carry, $key ) use ( $old_value, $value ) {
			if (
				// The old key and the new key have the same value.
				( isset( $old_value[ $key ] ) === isset( $value[ $key ] ) &&
				wp_json_encode( $old_value[ $key ] ) === wp_json_encode( $value[ $key ] ) ) ||

				// Ignoring the `parsely_wipe_metadata_cache` key if we're wiping the cache.
				( 'parsely_wipe_metadata_cache' === $key && ! empty( $value[ $key ] ) ) ||

				// Ignoring the `plugin_version` key.
				'plugin_version' === $key
			) {
				return $carry;
			}

			// The values are different, we're marking the current key as changed.
			$carry[] = $key;
			return $carry;
		},
		array()
	);

	if ( count( $updated_keys ) === 0 ) {
		return;
	}

	$telemetry_system->record_event( 'wpparsely_option_updated', compact( 'updated_keys' ) );
}
