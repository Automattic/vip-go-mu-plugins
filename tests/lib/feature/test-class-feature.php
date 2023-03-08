<?php

namespace Automattic\VIP;

use PHPUnit\Framework\TestCase;
use Automattic\Test\Constant_Mocker;

require_once __DIR__ . '/../../../lib/feature/class-feature.php';

class Feature_Test extends TestCase {

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
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', $site_id );

		Feature::$feature_percentages = array(
			$feature => $percentage,
		);

		$enabled = Feature::is_enabled_by_percentage( $feature );

		$this->assertEquals( $expected, $enabled );
	}

	public function test_is_enabled_by_percentage_using_constant() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 1 );

		Feature::$feature_percentages = array(
			'foo-feature' => 0.75,
		);

		$enabled = Feature::is_enabled_by_percentage( 'foo-feature' );

		$this->assertEquals( true, $enabled );
	}

	public function test_is_enabled_by_percentage_with_undefined_feature() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 1 );

		Feature::$feature_percentages = array(
			'foo' => 1,
		);

		$enabled = Feature::is_enabled_by_percentage( 'bar' );

		$this->assertEquals( false, $enabled );
	}

	public function test_is_enabled_by_percentage_with_is_enabled_by_ids() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 1 );

		Feature::$feature_percentages = array(
			'foo' => 1,
		);

		$enabled = Feature::is_enabled_by_percentage( 'bar' );

		$this->assertEquals( false, $enabled );
	}

	public function test_is_enabled_by_ids() {
		Feature::$feature_ids = [
			'foo'  => [
				123 => true,
				345 => true,
				789 => false,
			],
			'bar'  => [ 456 => true ],
			'test' => [ 456 => false ],
		];

		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 456 );

		$result = Feature::is_enabled_by_ids( 'foo' );

		$this->assertEquals( false, $result );

		$result = Feature::is_enabled_by_ids( 'bar' );

		$this->assertEquals( true, $result );

		$result = Feature::is_enabled_by_ids( 'test' );

		$this->assertEquals( false, $result );

		$result = Feature::is_enabled_by_ids( 'feature-not-exist' );

		$this->assertEquals( false, $result );
	}

	public function test_is_enabled_by_env__non_prod() {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );

		Feature::$feature_envs = array(
			'non-prod-feature-only' => [ 'non-production' => true ],
		);

		$result = Feature::is_enabled_by_env( 'non-prod-feature-only' );
		$this->assertTrue( $result );
	}

	public function test_is_enabled_by_env__staging() {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'staging' );

		Feature::$feature_envs = array(
			'staging-feature-only' => [ 'staging' => true ],
		);

		$result = Feature::is_enabled_by_env( 'staging-feature-only' );
		$this->assertTrue( $result );

		Constant_Mocker::clear();

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		$result = Feature::is_enabled_by_env( 'staging-feature-only' );
		$this->assertFalse( $result );
	}

	public function test_is_enabled_by_env__other() {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );

		Feature::$feature_envs = array(
			'other-feature-only' => [ 'other' => true ],
		);

		$result = Feature::is_enabled_by_env( 'other-feature-only' );
		$this->assertFalse( $result );
	}

	public function test_is_enabled_by_env__no_prod() {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		Feature::$feature_envs = array(
			'no-prod' => [
				'production' => false,
				'local'      => true,
			],
		);

		$result = Feature::is_enabled_by_env( 'no-prod' );
		$this->assertFalse( $result );

		Constant_Mocker::clear();
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
		$result = Feature::is_enabled_by_env( 'no-prod' );
		$this->assertTrue( $result );
	}

	public function test_is_enabled__percentage_only() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 456 );

		Feature::$feature_percentages = array(
			'foobar' => 1,
		);

		$result = Feature::is_enabled( 'foobar' );

		$this->assertTrue( $result );
	}

	public function test_is_enabled__id_only() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 123 );

		Feature::$feature_ids = array(
			'foo-bar' => [ 123 => true ],
		);

		$result = Feature::is_enabled( 'foo-bar' );

		$this->assertTrue( $result );
	}

	public function test_is_enabled__env_only() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 123 );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );

		Feature::$feature_envs = array(
			'foo-bar-feature' => [ 'local' => true ],
		);

		$result = Feature::is_enabled( 'foo-bar-feature' );

		$this->assertTrue( $result );
	}

	public function test_is_enabled__none() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 123 );

		$result = Feature::is_enabled( 'foo-bar-test' );

		$this->assertFalse( $result );
	}
}
