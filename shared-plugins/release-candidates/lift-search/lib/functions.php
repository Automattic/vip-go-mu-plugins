<?php

/**
 * Helper functions that may be needed.
 */
if ( !function_exists( 'array_diff_semi_assoc_recursive' ) ) {

	/**
	 * Returns the items in the first array that aren't in the second array.  Arrays
	 * are recursively compared.  If a value in the array is set by a String key, then
	 * that same key is checked in array2, otherwise, the existance of the value
	 * in array2 is checked.
	 * @param array $array1
	 * @param array $array2
	 * @return array
	 */
	function array_diff_semi_assoc_recursive( $array1, $array2 ) {
		$difference = array( );
		foreach ( $array1 as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( !isset( $array2[$key] ) ) {
					$difference[$key] = $value;
				} elseif ( !is_array( $array2[$key] ) ) {
					$new_diff = array_diff_semi_assoc_recursive( $value, ( array ) $array2[$key] );
					if ( !empty( $new_diff ) )
						$difference[$key] = $new_diff;
				} else {
					$new_diff = array_diff_semi_assoc_recursive( $value, $array2[$key] );
					if ( !empty( $new_diff ) )
						$difference[$key] = $new_diff;
				}
			} else if ( is_string( $key ) && (!array_key_exists( $key, $array2 ) || $array2[$key] != $value ) ) {
				if ( !(isset( $array2[$key] ) && is_array( $array2[$key] ) && in_array( $value, $array2[$key] )) ) {
					$difference[$key] = $value;
				}
			} elseif ( is_int( $key ) && !in_array( $value, $array2 ) ) {
				$difference[] = $value;
			}
		}
		return $difference;
	}

}
