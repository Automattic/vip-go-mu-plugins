<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

function track_wpparsely_widget_updated( $instance, $new_instance, $old_instance, $widget_obj, Telemetry_System $telemetry_system ) {
	global $wp;
	$id_base = $widget_obj->id_base;
	if ( WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID !== $id_base ) {
		return $instance;
	}

	if (
		isset( $wp->query_vars['rest_route'] ) &&
		'/wp/v2/widget-types/parsely_recommended_widget/encode' === $wp->query_vars['rest_route']
	) {
		/**
		 * This is an "encode" call to preview the widget in the admin.
		 * Track this event seperately.
		 */
		$telemetry_system->record_event( 'wpparsely_widget_prepublish_change', compact( 'id_base' ) );
		return $instance;
	}

	$all_keys     = array_unique( array_merge( array_keys( $old_instance ), array_keys( $instance ) ) );
	$updated_keys = array_reduce(
		$all_keys,
		function( $carry, $key ) use ( $old_instance, $instance ) {
			if (
				isset( $old_instance[ $key ] ) === isset( $instance[ $key ] ) &&
				wp_json_encode( $old_instance[ $key ] ) === wp_json_encode( $instance[ $key ] )
			) {
				return $carry;
			}
			$carry[] = $key;
			return $carry;
		},
		array()
	);

	if ( count( $updated_keys ) ) {
		$telemetry_system->record_event( 'wpparsely_widget_updated', compact( 'id_base', 'updated_keys' ) );
	}

	return $instance;
}
