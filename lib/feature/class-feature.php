<?php

namespace Automattic\VIP;

class Feature {
	public static $feature_percentages = array(
		'comment_type_update_cron' => 0.75, // Percent of sites that can run the comment type update batch jobs
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

		// Which bucket is the site in - 100 possibilites, one for each percentage. We run this through crc32 with
		// the feature name so that the same sites aren't always the canaries
		$bucket = crc32( $feature . '-' . static::$site_id ) % 100;

		// Is the bucket enabled?
		$threshold = $percentage * 100; // $percentage is decimal

		return $bucket < $threshold; // If our 0-based bucket is inside our threshold, it's enabled
	}
}
