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
	 * Whether we should rerun a search in the customizer preview or not.
	 *
	 * @return bool
	 */
	static function should_rerun_search_in_customizer_preview() {
		// Only update when in a customizer preview and data is being posted.
		// Check for $_POST removes an extra update when the customizer loads.
		//
		// Note: We use $GLOBALS['wp_customize'] here instead of is_customize_preview() to support unit tests.
		if ( ! isset( $GLOBALS['wp_customize'] ) || ! $GLOBALS['wp_customize']->is_preview() || empty( $_POST ) ) {
			return false;
		}

		return true;
	}

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
					? esc_html_x( 'Year Updated', 'label for filtering posts', 'vip-search' )
					: esc_html_x( 'Year', 'label for filtering posts', 'vip-search' );
				break;
			case 'month':
			default:
				$string = ( $is_updated )
					? esc_html_x( 'Month Updated', 'label for filtering posts', 'vip-search' )
					: esc_html_x( 'Month', 'label for filtering posts', 'vip-search' );
				break;
		}

		return $string;
	}
}