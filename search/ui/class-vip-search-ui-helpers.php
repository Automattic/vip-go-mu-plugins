<?php

namespace Automattic\VIP\Search\UI;

/**
 * Various helper functions for reuse throughout the Jetpack Search code.
 * @package Automattic\VIP\Search\UI
 *
 * Derived from Jetpack_Search_Widget
 */
class VIP_Search_UI_Helpers {

	/**
	 * Creates a default name for a filter. Used when the filter label is blank.
	 *
	 * @param array $widget_filter The filter to generate the title for.
	 *
	 * @return string The suggested filter name.
	 */
	static function generate_widget_filter_name( $widget_filter ) {
		$name = '';

		if ( ! isset( $widget_filter['type'] ) ) {
			return $name;
		}

		switch ( $widget_filter['type'] ) {
			case 'post_type':
				$name = _x( 'Post Types', 'label for filtering posts', 'jetpack' );
				break;

			case 'date_histogram':
				$modified_fields = array(
					'post_modified',
					'post_modified_gmt',
				);
				switch ( $widget_filter['interval'] ) {
					case 'year':
						$name = self::get_date_filter_type_name(
							'year',
							in_array( $widget_filter['field'], $modified_fields )
						);
						break;
					case 'month':
					default:
						$name = self::get_date_filter_type_name(
							'month',
							in_array( $widget_filter['field'], $modified_fields )
						);
						break;
				}
				break;

			case 'taxonomy':
				$tax = get_taxonomy( $widget_filter['taxonomy'] );
				if ( ! $tax ) {
					break;
				}

				if ( isset( $tax->label ) ) {
					$name = $tax->label;
				} elseif ( isset( $tax->labels ) && isset( $tax->labels->name ) ) {
					$name = $tax->labels->name;
				}
				break;
		}

		return $name;
	}

	/**
	 * Get the localized default label for a date filter.
	 *
	 * @param string $type       Date type, either year or month.
	 * @param bool   $is_updated Whether the filter was updated or not (adds "Updated" to the end).
	 *
	 * @return string The filter label.
	 */
	static function get_date_filter_type_name( $type, $is_updated = false ) {
		switch ( $type ) {
			case 'year':
				$string = ( $is_updated )
					? esc_html_x( 'Year Updated', 'label for filtering posts', 'jetpack' )
					: esc_html_x( 'Year', 'label for filtering posts', 'jetpack' );
				break;
			case 'month':
			default:
				$string = ( $is_updated )
					? esc_html_x( 'Month Updated', 'label for filtering posts', 'jetpack' )
					: esc_html_x( 'Month', 'label for filtering posts', 'jetpack' );
				break;
		}

		return $string;
	}
}