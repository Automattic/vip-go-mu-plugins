<?php

namespace Automattic\VIP;

/**
 * Feature provides a simple interface to gate the functionality by the Go Site Id
 *
 * To register a feature add it to the $feature_percentages array (per example below)
 *
 * To check whether a feature is enabled: \Automattic\VIP::is_enabled( 'feature-flag' )
 */
class Feature {
	/**
	 * Holds feature slug and corresponding percentages as a float. E.g.
	 * // Enable feature for roughly half of the websites
	 * // 'feature-flag' => 0.5,
	 *
	 * @var array
	 */
	public static $feature_percentages = [
		'es-delete-index' => 0.25,
	];

	/**
	 * Holds feature slug and then, key of ids with bool value to enable E.g.
	 * // Enable feature for sites 123 and 345 and disable 567
	 * // 'feature-flag' => [ 123 => true, 345 => true, 567 => false ],
	 *
	 * @var array
	 */
	public static $feature_ids = [];

	public static function is_enabled( $feature ) {
		return static::is_enabled_by_percentage( $feature );
	}

	/**
	 * Selectively enable or disable feature by certain IDs.
	 * 
	 * @param string $feature The feature we are targeting.
	 * @param mixed $default Default return value if ID is not on list.
	 * 
	 * @return mixed Returns bool if on list and if not, $default value.  
	 */
	public static function is_enabled_by_ids( $feature, $default = false ) {
		if ( ! isset( static::$feature_ids[ $feature ] ) ) {
			return false;
		}

		if ( array_key_exists( constant( 'FILES_CLIENT_SITE_ID' ), static::$feature_ids[ $feature ] ) ) {
			return static::$feature_ids[ $feature ][ constant( 'FILES_CLIENT_SITE_ID' ) ];
		}

		return $default;
	}

	/**
	 * Roll out based on percentage.
	 * 
	 * @param string $feature The feature we are targeting.
	 * 
	 * @return bool Whether it is enabled or not. 
	 */
	public static function is_enabled_by_percentage( $feature ) {
		if ( ! isset( static::$feature_percentages[ $feature ] ) ) {
			return false;
		}

		$percentage = static::$feature_percentages[ $feature ];

		// Which bucket is the site in - 100 possibilites, one for each percentage. We run this through crc32 with
		// the feature name so that the same sites aren't always the canaries
		$bucket = crc32( $feature . '-' . constant( 'FILES_CLIENT_SITE_ID' ) ) % 100;

		// Is the bucket enabled?
		$threshold = $percentage * 100; // $percentage is decimal

		return $bucket < $threshold; // If our 0-based bucket is inside our threshold, it's enabled
	}
}
