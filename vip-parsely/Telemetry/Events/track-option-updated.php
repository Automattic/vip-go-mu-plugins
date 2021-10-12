<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

function track_option_updated( $old_value, $value, Telemetry_System $telemetry_system ): void {
	$all_keys     = array_unique( array_merge( array_keys( $old_value ), array_keys( $value ) ) );
	$updated_keys = array_reduce(
		$all_keys,
		function( $carry, $key ) use ( $old_value, $value ) {
			if (
				isset( $old_value[ $key ] ) === isset( $value[ $key ] ) &&
				wp_json_encode( $old_value[ $key ] ) === wp_json_encode( $value[ $key ] )
			) {
				return $carry;
			}

			if ( 'parsely_wipe_metadata_cache' === $key && ! ( isset( $value[ $key ] ) && $value[ $key ] ) ) {
				return $carry;
			}

			if ( 'plugin_version' === $key ) {
				return $carry;
			}

			$carry[] = $key;
			return $carry;
		},
		array()
	);

	if ( ! count( $updated_keys ) ) {
		return;
	}
	$telemetry_system->record_event( 'wpparsely_option_updated', compact( 'updated_keys' ) );
}
