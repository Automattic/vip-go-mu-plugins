<?php

namespace Automattic\VIP;

if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
	$cli_file = __DIR__ . '/class-feature-cli.php';
	if ( file_exists( $cli_file ) ) {
		require_once $cli_file;
	}
}

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
	 * Holds feature slug and then, key of ids with bool value to enable E.g.
	 * // Enable feature for sites 123 and 345 and disable 567
	 * // 'feature-flag' => [ 123 => true, 345 => true, 567 => false ],
	 *
	 * @var array
	 */
	public static $feature_ids = [
		'prom-user-collection' => [
			8821 => true,
		],
	];

	/**
	 * Holds feature slug and then, key of environments with bool value to enable E.g.
	 * // Enable feature for non-production sites
	 * // 'feature-flag' => [ 'non-production' => true ],
	 *
	 *
	 * @var array Array of values of specific environment names (i.e. staging, production). Also accepts 'non-production' as environment name for all non-production environments.
	 */
	public static $feature_envs = [
		'prom-user-collection' => [
			'develop' => true,
			'staging' => true,
		],
	];

	/**
	 * Checks if a feature is enabled.
	 *
	 * @param string $feature The feature we are targeting.
	 *
	 * @return bool Whether it is enabled or not.
	 */
	public static function is_enabled( string $feature ) {
		if ( true === static::is_disabled_by_ids( $feature ) ) {
			return false;
		}

		return static::is_enabled_by_percentage( $feature ) || static::is_enabled_by_ids( $feature ) || static::is_enabled_by_env( $feature );
	}

	/**
	 * Returns all features that exist.
	 *
	 * @return array $features Array of features.
	 */
	public static function get_features() {
		$features = array_merge(
			is_array( static::$feature_percentages ) ? array_keys( static::$feature_percentages ) : [],
			is_array( static::$feature_ids ) ? array_keys( static::$feature_ids ) : [],
			is_array( static::$feature_envs ) ? array_keys( static::$feature_envs ) : [],
		);

		return array_unique( $features );
	}


	/**
	 * Selectively enable by certain environments.
	 *
	 * @param string $feature The feature we are targeting.
	 * @param mixed $default Default return value if environment is not on list.
	 *
	 * @return mixed Returns bool if on list and if not, $default value.
	 */
	public static function is_enabled_by_env( string $feature, $default = false ) {
		if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			return false;
		}

		if ( ! isset( static::$feature_envs[ $feature ] ) ) {
			return false;
		}

		$envs = static::$feature_envs[ $feature ];
		if ( array_key_exists( 'non-production', $envs ) && true === $envs['non-production'] ) {
			if ( constant( 'VIP_GO_APP_ENVIRONMENT' ) !== 'production' ) {
				return true;
			}
		}

		if ( array_key_exists( constant( 'VIP_GO_APP_ENVIRONMENT' ), $envs ) ) {
			return $envs[ constant( 'VIP_GO_APP_ENVIRONMENT' ) ];
		}

		return $default;
	}

	/**
	 * Selectively disable feature by certain IDs.
	 *
	 * @param string $feature The feature we are targeting.
	 *
	 * @return mixed Returns true if on list.
	 */
	public static function is_disabled_by_ids( string $feature ) {
		if ( ! isset( static::$feature_ids[ $feature ] ) || ! defined( 'FILES_CLIENT_SITE_ID' ) ) {
			return false;
		}

		if ( array_key_exists( constant( 'FILES_CLIENT_SITE_ID' ), static::$feature_ids[ $feature ] ) ) {
			return false === static::$feature_ids[ $feature ][ constant( 'FILES_CLIENT_SITE_ID' ) ];
		}

		return false;
	}

	/**
	 * Selectively enable feature by certain IDs.
	 *
	 * @param string $feature The feature we are targeting.
	 *
	 * @return bool Returns true if on list.
	 */
	public static function is_enabled_by_ids( string $feature ) {
		if ( ! isset( static::$feature_ids[ $feature ] ) || ! defined( 'FILES_CLIENT_SITE_ID' ) ) {
			return false;
		}

		if ( array_key_exists( constant( 'FILES_CLIENT_SITE_ID' ), static::$feature_ids[ $feature ] ) ) {
			return true === static::$feature_ids[ $feature ][ constant( 'FILES_CLIENT_SITE_ID' ) ];
		}

		return false;
	}

	/**
	 * Roll out based on percentage.
	 *
	 * @param string $feature The feature we are targeting.
	 *
	 * @return bool Whether it is enabled or not.
	 */
	public static function is_enabled_by_percentage( string $feature ) {
		if ( ! defined( 'FILES_CLIENT_SITE_ID' ) ) {
			return false;
		}

		if ( ! isset( static::$feature_percentages[ $feature ] ) ) {
			return false;
		}

		$percentage = static::$feature_percentages[ $feature ];

		// Which bucket is the site in - 100 possibilites, one for each percentage. We run this through crc32 with
		// the feature name so that the same sites aren't always the canaries
		$bucket = static::get_bucket( constant( 'FILES_CLIENT_SITE_ID' ), $feature );

		// Is the bucket enabled?
		$threshold = $percentage * 100; // $percentage is decimal

		if ( is_multisite() && $bucket < $threshold ) {
			// For multisites, we don't want to roll out for all subsites
			$bucket = static::get_bucket( get_current_blog_id(), $feature );
		}

		return $bucket < $threshold; // If our 0-based bucket is inside our threshold, it's enabled
	}

	/**
	 * Given a site ID and a feature, return the bucket the site is in.
	 *
	 * Note: this is a lower-level API, it doesn't care if the feature is defined or whether it's a multisite or not.
	 * Please use is_enabled()/is_enabled_by_ids()/is_enabled_by_percentage() instead.
	 *
	 * @param mixed $site_id
	 * @param string $feature
	 * @return integer
	 */
	public static function get_bucket( $site_id = 0, string $feature = '' ) {
		// Which bucket is the site in - 100 possibilites, one for each percentage. We run this through crc32 with
		// the feature name so that the same sites aren't always the canaries
		$bucket = crc32( $feature . '-' . $site_id ) % 100;

		return $bucket;
	}
}
