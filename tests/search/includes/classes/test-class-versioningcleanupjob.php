<?php

namespace Automattic\VIP\Search;

use ElasticPress\Indexable;
use ElasticPress\Indexables;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

class VersioningCleanupJob_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/../../../../search/includes/classes/class-versioningcleanupjob.php';
	}

	public function test__versioning_cleanup__will_check_for_all_indexables() {
		$indexables_mocks = array_map( function ( $slug ) {
			$indexable_mock       = $this->getMockBuilder( Indexable::class )->getMock();
			$indexable_mock->slug = $slug;
			return $indexable_mock;
		}, [ 'foo', 'bar' ] );

		$indexables_mock = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get_all' ] )
			->getMock();
		$indexables_mock->method( 'get_all' )->willReturn( $indexables_mocks );

		$versioning_mock = $this->getMockBuilder( Versioning::class )
			->onlyMethods( [ 'delete_version' ] )
			->getMock();

		/** @var MockObject&VersioningCleanupJob */
		$partially_mocked_instance = $this->getMockBuilder( VersioningCleanupJob::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'delete_stale_inactive_version', 'get_stale_inactive_versions' ] )
			->getMock();

		$partially_mocked_instance
			->method( 'get_stale_inactive_versions' )
			->willReturn( [ [ 'number' => 1 ], [ 'number' => 2 ] ] );

		$partially_mocked_instance->indexables = $indexables_mock;
		$partially_mocked_instance->versioning = $versioning_mock;

		$call_index = 0;
		$partially_mocked_instance->expects( $this->exactly( 4 ) )
			->method( 'delete_stale_inactive_version' )
			->with( $this->callback( function ( $indexable ) use ( $indexables_mocks, &$call_index ) {
				$expected_index  = intdiv( $call_index, 2 );
				$expected_number = $call_index % 2 + 1;
				++$call_index;
				return $indexable === $indexables_mocks[ $expected_index ] && $expected_number;
			}));

		$partially_mocked_instance->versioning_cleanup();
	}

	public function get_stale_inactive_versions_data() {
		return [
			[
				[],
				[],
				[],
			],
			[
				[
					1 => [
						'number'         => 1,
						'active'         => true,
						'created_time'   => null,
						'activated_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
					],
					2 => [
						'number'       => 2,
						'active'       => false,
						'created_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
					],
				],
				[
					'number'         => 1,
					'active'         => true,
					'created_time'   => null,
					'activated_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
				],
				[ 2 ],
			],
			[
				// Recently created version is not inactive
				[
					1 => [
						'number'         => 1,
						'active'         => true,
						'created_time'   => null,
						'activated_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
					],
					2 => [
						'number'       => 2,
						'active'       => false,
						'created_time' => time() - ( 2 * \DAY_IN_SECONDS ),
					],
				],
				[
					'number'         => 1,
					'active'         => true,
					'created_time'   => null,
					'activated_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
				],
				[],
			],
			[
				// If the active version was activated recently no version is inactive
				[
					1 => [
						'number'         => 1,
						'active'         => true,
						'created_time'   => null,
						'activated_time' => time() - ( 2 * \DAY_IN_SECONDS ),
					],
					2 => [
						'number'       => 2,
						'active'       => false,
						'created_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
					],
				],
				[
					'number'         => 1,
					'active'         => true,
					'created_time'   => null,
					'activated_time' => time() - ( 2 * \DAY_IN_SECONDS ),
				],
				[],
			],
			[
				// Versions without created_time (possibly recovered by self-healing) won't be reported as inactive
				[
					2 => [
						'number'       => 2,
						'active'       => false,
						'created_time' => null,
					],
				],
				[
					'number'         => 1,
					'active'         => true,
					'created_time'   => null,
					'activated_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
				],
				[],
			],
			[
				// Versions that are 1 without created_time are reported as inactive
				[
					2 => [
						'number'       => 1,
						'active'       => false,
						'created_time' => null,
					],
				],
				[
					'number'         => 2,
					'active'         => true,
					'created_time'   => null,
					'activated_time' => time() - ( 2 * \MONTH_IN_SECONDS ),
				],
				[ 1 ],
			],
		];
	}

	/**
	 * @dataProvider get_stale_inactive_versions_data
	 */
	public function test__get_stale_inactive_versions( $input_versions, $active_version, $expected_numbers ) {
		$versions = $input_versions;

		/** @var MockObject&Indexable */
		$indexable_mock = $this->getMockBuilder( Indexable::class )->getMock();

		$versioning_mock = $this->getMockBuilder( Versioning::class )
			->onlyMethods( [ 'get_versions', 'get_active_version' ] )
			->getMock();
		$versioning_mock->method( 'get_versions' )->willReturn( $versions );
		$versioning_mock->method( 'get_active_version' )->willReturn( $active_version );
		$instance = new VersioningCleanupJob( null, $versioning_mock );

		$result = $instance->get_stale_inactive_versions( $indexable_mock );

		$result_numbers = array_map( function ( $element ) {
			return $element['number'];
		}, $result );

		$this->assertEquals( $expected_numbers, $result_numbers );
	}
}
