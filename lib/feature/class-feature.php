<?php

namespace Automattic\VIP;

class Feature {
	public static $feature_percentages = array(

	);

	public static $site_id = FILES_CLIENT_SITE_ID;

	public static function is_enabled( $feature ) {
		return static::is_enabled_by_percentage( $feature );
	}

	public static function is_enabled_by_percentage( $feature ) {
		if ( ! isset( static::$feature_percentages[ $feature ] ) ) {
			return false;
		}

		$percentage = static::$feature_percentages[ $feature ];

		// Which bucket is the site in - 100 possibilites, one for each percentage
		$bucket = static::$site_id % 100;

		// Is the bucket enabled?
		$threshold = $percentage * 100; // $percentage is decimal

		return $bucket < $threshold; // If our 0-based bucket is inside our threshold, it's enabled
	}
}
