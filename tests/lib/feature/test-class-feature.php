<?php

namespace Automattic\VIP;

class Feature_Test extends \PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../../lib/feature/class-feature.php' );
	}

	public function is_enabled_by_percentage_data() {
		return array(
			// Site ID bucketed within the percentage threshold, enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				1,
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
				101,
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
				125,
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
				124,
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
				126,
				// Expected enabled/disabled
				false,
			),
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// Site id
				126,
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

	public function test_is_enabled_by_percentage_with_undefined_feature() {
		Feature::$site_id = 1;

		Feature::$feature_percentages = array(
			'foo' => 1,
		);

		$enabled = Feature::is_enabled_by_percentage( 'bar' );

		$this->assertEquals( false, $enabled );
	}
}
