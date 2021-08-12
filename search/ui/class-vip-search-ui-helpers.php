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
	 * Since PHP's built-in array_diff() works by comparing the values that are in array 1 to the other arrays,
	 * if there are less values in array 1, it's possible to get an empty diff where one might be expected.
	 *
	 * @param array $array_1
	 * @param array $array_2
	 *
	 * @return array
	 *
	 */
	private static function array_diff( array $array_1, array $array_2 ) {
		// If the array counts are the same, then the order doesn't matter. If the count of
		// $array_1 is higher than $array_2, that's also fine. If the count of $array_2 is higher,
		// we need to swap the array order though.
		if ( count( $array_1 ) !== count( $array_2 ) && count( $array_2 ) > count( $array_1 ) ) {
			$temp    = $array_1;
			$array_1 = $array_2;
			$array_2 = $temp;
		}

		// Disregard keys
		return array_values( array_diff( $array_1, $array_2 ) );
	}

	/**
	 * Given the widget instance, will return true when selected post types differ from searchable post types.
	 *
	 * @param array $post_types An array of post types.
	 *
	 * @return bool
	 *
	 */
	static function post_types_differ_searchable( array $post_types ):bool {
		if ( empty( $post_types ) ) {
			return false;
		}

		$searchable_post_types = get_post_types( array( 'exclude_from_search' => false ) );
		$diff_of_searchable    = self::array_diff( $searchable_post_types, (array) $post_types );

		return ! empty( $diff_of_searchable );
	}

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