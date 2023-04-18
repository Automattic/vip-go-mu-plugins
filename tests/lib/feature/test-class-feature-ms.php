<?php

namespace Automattic\VIP;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

require_once __DIR__ . '/../../../lib/feature/class-feature.php';

class Feature_Multisite_Test extends WP_UnitTestCase {

	public static $site_id = 400; // Hashes to 13

	public function setUp(): void {
		$this->skipWithoutMultisite();

		parent::setUp();

		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', self::$site_id );

		remove_all_actions( 'switch_blog' );
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
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
		return [
			// Feature name, percentage, blog ID, expected enabled/disabled
			[ 'foo-feature', 0.25, 6, true ], // Blog ID bucketed within the percentage threshold, enabled; hashes to 4
			[ 'foo-feature', 0.25, 37, true ], // Blog id bucketed within the percentage threshold, enabled; hashes to 3
			[ 'foo-feature', 0.25, 20, false ], // blog id is bucketed to exact percentage, not enabled, b/c buckets are 0-based; hashes to 25
			[ 'foo-feature', 0.63, 99, true ], // blog id is bucketed to "percentage - 1", enabled, b/c buckets are 0-based; hashes to 62
			[ 'foo-feature', 0.25, 7, false ], // blog id bucketed outside the threshold, not enabled; hashes to 26
			[ 'foo-feature', 1.00, 100, true ], // 100% enabled
			[ 'foo-feature', 1.00, 5, true ],
			[ 'foo-feature', 0.00, 22, false ], // 0% enabled
			[ 'foo-feature', 0.00, 1, false ],
			[ 'bar-feature', 0.25, 37, false ], // Different feature name, should _not_ have the same bucket as the same id from earlier; hashes to 90
		];
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
