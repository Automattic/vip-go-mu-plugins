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
	public static $feature_percentages = [];

	/**
	 * Holds feature slug and then, array of ids E.g.
	 * // Enable feature for sites 123, 345 and 567
	 * // 'feature-flag' => [ 123, 345, 567 ],
	 *
	 * @var array
	 */
	public static $feature_ids = [];

	public static $site_id = false;

	public static function is_enabled( $feature ) {
		return static::is_enabled_by_percentage( $feature );
	}

	/**
	 * Selectively enable feature by certain IDs.
	 * 
	 * @param array $ids Site IDs to enable the feature for.
	 */
	public static function is_enabled_by_ids( $feature ) {
		if ( in_array( FILES_CLIENT_SITE_ID, static::$feature_ids[ $feature ], true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Selectively disable feature for certain IDs.
	 * 
	 * @param array $ids Site IDs to enable the feature for.
	 */
	public static function is_disabled_by_ids( $feature ) {
		if ( in_array( FILES_CLIENT_SITE_ID, static::$feature_ids[ $feature ], true ) ) {
			return false;
		}
		return true;
	}

	public static function is_enabled_by_percentage( $feature ) {
		if ( ! isset( static::$feature_percentages[ $feature ] ) ) {
			return false;
		}

		$percentage = static::$feature_percentages[ $feature ];

		// Which bucket is the site in - 100 possibilites, one for each percentage. We run this through crc32 with
		// the feature name so that the same sites aren't always the canaries
		$bucket = crc32( $feature . '-' . static::site_id() ) % 100;

		// Is the bucket enabled?
		$threshold = $percentage * 100; // $percentage is decimal

		return $bucket < $threshold; // If our 0-based bucket is inside our threshold, it's enabled
	}

	public static function site_id() {
		// This is used in tests, so the site id can be easily changed
		if ( static::$site_id ) {
			return static::$site_id;
		}

		return defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : 0;
	}
}
