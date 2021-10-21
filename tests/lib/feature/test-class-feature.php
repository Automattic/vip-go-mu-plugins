<?php

namespace Automattic\VIP;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../lib/feature/class-feature.php';

class Feature_Test extends TestCase {
	/**
	 * NOTE - since the Feature class uses crc32 on the feature + id (to distribute testing across sites), we have to
	 * use something like this when generating test data:
	 * 
	 * for( $i = 1; $i < 1000; $i++ ) {
	 *     echo $i . ' - ' . crc32( 'foo-feature-' . $i ) % 100 . PHP_EOL;
	 * }
	 * 
	 * The above will give you a list of site IDs that fall above or below your target threshold
	 */
	public function is_enabled_by_percentage_data() {
		return array(
			// Site ID bucketed within the percentage threshold, enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				6, // hashes to 4
				// Expected enabled/disabled
				true,
			),
			// Site ID bucketed within the percentage threshold, enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				37, // hashes to 3
				// Expected enabled/disabled
				true,
			),
			// Site ID is bucketed to exact percentage, not enabled, b/c buckets are 0-based
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				20, // hashes to 25
				// Expected enabled/disabled
				false,
			),
			// Site ID is bucketed to "percentage - 1", enabled, b/c buckets are 0-based
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				995, // hashes to 24
				// Expected enabled/disabled
				true,
			),
			// Site ID bucketed outside the threshold, not enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				7, // hashes to 26
				// Expected enabled/disabled
				false,
			),
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				21, // hashes to 91
				// Expected enabled/disabled
				false,
			),

			// 100% enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				1,
				// Site id
				100,
				// Expected enabled/disabled
				true,
			),
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				1,
				// Site id
				100000,
				// Expected enabled/disabled
				true,
			),

			// 0% enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0,
				// Site id
				100,
				// Expected enabled/disabled
				false,
			),
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0,
				// Site id
				999999,
				// Expected enabled/disabled
				false,
			),

			// Different feature name, should _not_ have the same bucket as the same id from earlier
			array(
				// Feature name
				'bar-feature',
				// Enabled percentage
				0.25,
				// Site id
				37, // hashes to 90
				// Expected enabled/disabled
				false,
			),
		);
	}

	/**
	 * @dataProvider is_enabled_by_percentage_data
	 */
	public function test_is_enabled_by_percentage( $feature, $percentage, $site_id, $expected ) {
		Feature::$site_id = $site_id;

		Feature::$feature_percentages = array(
			$feature => $percentage,
		);

		$enabled = Feature::is_enabled_by_percentage( $feature );

		$this->assertEquals( $expected, $enabled );
	}

	public function test_is_enabled_by_percentage_using_constant() {
		Feature::$site_id = false;

		// Feature will use FILES_CLIENT_SITE_ID, which is 123 in tests, when it isn't set on the class

		Feature::$feature_percentages = array(
			'foo-feature' => 0.75,
		);

		$enabled = Feature::is_enabled_by_percentage( 'foo-feature' );

		$this->assertEquals( true, $enabled );
	}

	public function test_is_enabled_by_percentage_with_undefined_feature() {
		Feature::$site_id = 1;

		Feature::$feature_percentages = array(
			'foo' => 1,
		);

		$enabled = Feature::is_enabled_by_percentage( 'bar' );

		$this->assertEquals( false, $enabled );
	}
}
