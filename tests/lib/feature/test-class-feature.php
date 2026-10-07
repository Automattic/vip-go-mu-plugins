<?php

namespace Automattic\VIP;

use PHPUnit\Framework\TestCase;
use Automattic\Test\Constant_Mocker;

require_once __DIR__ . '/../../../lib/feature/class-feature.php';

class Feature_Test extends TestCase {
	/** @var array{0: array, 1: array, 2: array} The registered feature percentages, IDs and environments. */
	private $original_features;

	public function setUp(): void {
		parent::setUp();
		$this->original_features = [ Feature::$feature_percentages, Feature::$feature_ids, Feature::$feature_envs ];
	}

	public function tearDown(): void {
		[ Feature::$feature_percentages, Feature::$feature_ids, Feature::$feature_envs ] = $this->original_features;
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

			// 75% enabled
			array(
				// Feature name
				'foo-feature',
				// Enabled percentage
				0.75,
				// Site id
				1,
				// Expected enabled/disabled
				true,
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

	public function test_is_enabled_by_percentage_with_undefined_feature() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 1 );

		Feature::$feature_percentages = array(
			'foo' => 1,
		);

		$enabled = Feature::is_enabled_by_percentage( 'bar' );

		$this->assertEquals( false, $enabled );
	}

	public function get_test_data__by_ids() {
		return [
			'site not listed'   => [ 'foo', false, false ],
			'site enabled'      => [ 'bar', true, false ],
			'site disabled'     => [ 'test', false, true ],
			'feature not exist' => [ 'feature-not-exist', false, false ],
		];
	}

	/**
	 * @dataProvider get_test_data__by_ids
	 */
	public function test_is_enabled_and_disabled_by_ids( $feature, $expected_enabled, $expected_disabled ) {
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

		$this->assertSame( $expected_enabled, Feature::is_enabled_by_ids( $feature ) );
		$this->assertSame( $expected_disabled, Feature::is_disabled_by_ids( $feature ) );
	}

	public function get_test_data__is_enabled_by_env() {
		return [
			'non-production flag on local'          => [ [ 'non-production' => true ], 'local', true ],
			'staging flag on staging'               => [ [ 'staging' => true ], 'staging', true ],
			'staging flag on production'            => [ [ 'staging' => true ], 'production', false ],
			'unknown environment flag'              => [ [ 'other' => true ], 'local', false ],
			'disabled on production'                => [
				[
					'production' => false,
					'local'      => true,
				],
				'production',
				false,
			],
			'disabled on production, local enabled' => [
				[
					'production' => false,
					'local'      => true,
				],
				'local',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_enabled_by_env
	 */
	public function test_is_enabled_by_env( $envs, $environment, $expected ) {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', $environment );

		Feature::$feature_envs = array(
			'env-feature' => $envs,
		);

		$this->assertSame( $expected, Feature::is_enabled_by_env( 'env-feature' ) );
	}

	public function get_test_data__is_enabled() {
		return [
			'percentage only'                          => [ 456, [ 'feature' => 1 ], [], [], true ],
			'ID only'                                  => [ 123, [], [ 'feature' => [ 123 => true ] ], [], true ],
			'environment only'                         => [ 123, [], [], [ 'feature' => [ 'local' => true ] ], true ],
			'none'                                     => [ 123, [], [], [], false ],
			'disabled ID'                              => [ 123, [], [ 'feature' => [ 123 => false ] ], [], false ],
			'disabled ID overrides percentage rollout' => [ 123, [ 'feature' => 1 ], [ 'feature' => [ 123 => false ] ], [], false ],
			'disabled ID overrides environment grant'  => [ 123, [], [ 'feature' => [ 123 => false ] ], [ 'feature' => [ 'local' => true ] ], false ],
			'explicit enable with overlapping grants'  => [ 123, [ 'feature' => 1 ], [ 'feature' => [ 123 => true ] ], [ 'feature' => [ 'local' => true ] ], true ],
		];
	}

	/**
	 * @dataProvider get_test_data__is_enabled
	 */
	public function test_is_enabled( $site_id, $percentages, $ids, $envs, $expected ) {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', $site_id );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );

		Feature::$feature_percentages = $percentages;
		Feature::$feature_ids         = $ids;
		Feature::$feature_envs        = $envs;

		$this->assertSame( $expected, Feature::is_enabled( 'feature' ) );
	}

	public function test_get_features() {
		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', 123 );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'local' );

		Feature::$feature_percentages = array(
			'foo-bar-aaa' => 1,
		);

		Feature::$feature_ids  = array(
			'foo-bar-zzz' => [ 123 => true ],
		);
		Feature::$feature_envs = array(
			'foo-bar-zzz' => [ 'local' => true ],
		);

		$result = Feature::get_features();
		$this->assertEquals( $result, [ 'foo-bar-aaa', 'foo-bar-zzz' ] );
	}
}
