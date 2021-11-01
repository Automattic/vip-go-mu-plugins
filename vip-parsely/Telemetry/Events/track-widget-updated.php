<?php
/**
 * Tracking code for the `widget_update_callback` filter.
 *
 * @package Automattic\VIP\Parsely\Telemetry
 */

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_Widget;

/**
 * Records an event using the given Telemetry System whenever a `parsely_recommended_widget` instance is updated.
 *
 * @param array $instance The current widget instance's settings.
 * @param array|null $new_instance Array of new widget settings.
 * @param array|null $old_instance Array of old widget settings.
 * @param WP_Widget $widget_obj The current widget instance.
 * @param Telemetry_System $telemetry_system
 * @return array Updated widget settings
 */
function track_widget_updated( array $instance, ?array $new_instance, ?array $old_instance, WP_Widget $widget_obj, Telemetry_System $telemetry_system ): array {
	$id_base = $widget_obj->id_base;
	if ( WP_PARSELY_RECOMMENDED_WIDGET_BASE_ID !== $id_base ) {
		return $instance;
	}

	global $wp;
	if (
		isset( $wp->query_vars['rest_route'] ) &&
		'/wp/v2/widget-types/parsely_recommended_widget/encode' === $wp->query_vars['rest_route']
	) {
		/**
		 * This is an "encode" call to preview the widget in the admin.
		 * Track this event separately.
		 */
		$telemetry_system->record_event( 'wpparsely_widget_prepublish_change', compact( 'id_base' ) );
		return $instance;
	}

	if ( null == $old_instance ) {
		// If there is no old instance, all keys are updated. We have this shortcut so we don't have to do
		// `array_keys` of null, thus raising a warning.
		$updated_keys = array_keys( $instance );
	} else {
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
	}

	if ( count( $updated_keys ) ) {
		$telemetry_system->record_event( 'wpparsely_widget_updated', compact( 'id_base', 'updated_keys' ) );
	}

	return $instance;
}
