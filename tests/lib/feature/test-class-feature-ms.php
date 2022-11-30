<?php

namespace Automattic\VIP;

use PHPUnit\Framework\TestCase;
use Automattic\Test\Constant_Mocker;

require_once __DIR__ . '/../../../lib/feature/class-feature.php';

class Feature_Multisite_Test extends TestCase {

	public static $site_id = 400; // Hashes to 13

	public function setUp(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		parent::setUp();

		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', self::$site_id );
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
	}

	/**
	 * NOTE - since the Feature class uses crc32 on the feature + id (to distribute testing across sites), we have to
	 * use something like this when generating test data:
	 *
	 * for( $i = 1; $i < 1000; $i++ ) {
	 *     echo $i . ' - ' . crc32( 'foo-feature-' . $i ) % 100 . PHP_EOL;
	 * }
	 *
	 * The above will give you a list of blog ids that fall above or below your target threshold
	 */
	public function is_enabled_by_percentage_data() {
		return array(
			// Blog ID bucketed within the percentage threshold, enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// blog id
				6, // hashes to 4
				// Expected enabled/disabled
				true,
			),
			// blog id bucketed within the percentage threshold, enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// blog id
				37, // hashes to 3
				// Expected enabled/disabled
				true,
			),
			// blog id is bucketed to exact percentage, not enabled, b/c buckets are 0-based
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// blog id
				20, // hashes to 25
				// Expected enabled/disabled
				false,
			),
			// blog id is bucketed to "percentage - 1", enabled, b/c buckets are 0-based
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.63,
				// blog id
				99, // hashes to 62
				// Expected enabled/disabled
				true,
			),
			// blog id bucketed outside the threshold, not enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.25,
				// blog id
				7, // hashes to 26
				// Expected enabled/disabled
				false,
			),

			// 100% enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				1,
				// blog id
				100,
				// Expected enabled/disabled
				true,
			),
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				1,
				// blog id
				5,
				// Expected enabled/disabled
				true,
			),

			// 0% enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0,
				// blog id
				22,
				// Expected enabled/disabled
				false,
			),
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0,
				// blog id
				1,
				// Expected enabled/disabled
				false,
			),

			// Different feature name, should _not_ have the same bucket as the same id from earlier
			array(
				// Feature name
				'bar-feature',
				// Enabled percentage
				0.25,
				// blog id
				37, // hashes to 90
				// Expected enabled/disabled
				false,
			),
		);
	}

	/**
	 * @dataProvider is_enabled_by_percentage_data
	 */
	public function test_is_enabled_by_percentage( $feature, $percentage, $blogid, $expected ) {
		Feature::$feature_percentages = array(
			$feature => $percentage,
		);

		switch_to_blog( $blogid );

		$enabled = Feature::is_enabled_by_percentage( $feature );

		$this->assertEquals( $expected, $enabled );

		restore_current_blog();
	}

	public function test_is_enabled_by_percentage_with_undefined_feature() {
		Feature::$feature_percentages = array(
			'foo' => 1,
		);

		switch_to_blog( wp_rand( 0, 20 ) );

		$enabled = Feature::is_enabled_by_percentage( 'barzzz' );

		$this->assertEquals( false, $enabled );

		restore_current_blog();
	}
}
