<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;

class VersioningCleanupJob_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/../../../../search/includes/classes/class-versioningcleanupjob.php';
	}

	public function test__versioning_cleanup__will_check_for_all_indexables() {
		$indexables_mocks = array_map( function( $slug ) {
			$indexable_mock       = $this->getMockBuilder( \ElasticPress\Indexable::class )->getMock();
			$indexable_mock->slug = $slug;
			return $indexable_mock;
		}, [ 'foo', 'bar' ] );

		$indexables_mock = $this->getMockBuilder( \ElasticPress\Indexables::class )
			->setMethods( [ 'get_all' ] )
			->getMock();
		$indexables_mock->method( 'get_all' )->willReturn( $indexables_mocks );


		$partially_mocked_instance = $this->getMockBuilder( \Automattic\VIP\Search\VersioningCleanupJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'send_notification', 'get_stale_inactive_versions' ] )
			->getMock();

		$partially_mocked_instance
			->method( 'get_stale_inactive_versions' )
			->willReturn( [ [ 'number' => 1 ], [ 'number' => 2 ] ] );

		$partially_mocked_instance->indexables = $indexables_mock;


		$partially_mocked_instance->expects( $this->exactly( 4 ) )
			->method( 'send_notification' )
			->withConsecutive(
				[ $this->equalTo( 'foo' ), $this->equalTo( 1 ) ],
				[ $this->equalTo( 'foo' ), $this->equalTo( 2 ) ],
				[ $this->equalTo( 'bar' ), $this->equalTo( 1 ) ],
				[ $this->equalTo( 'bar' ), $this->equalTo( 2 ) ]
			);

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
						'activated_time' => time() - ( 35 * \DAY_IN_SECONDS ),
					],
					2 => [
						'number'       => 2,
						'active'       => false,
						'created_time' => time() - ( 35 * \DAY_IN_SECONDS ),
					],
				],
				[
					'number'         => 1,
					'active'         => true,
					'created_time'   => null,
					'activated_time' => time() - ( 35 * \DAY_IN_SECONDS ),
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
						'activated_time' => time() - ( 35 * \DAY_IN_SECONDS ),
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
					'activated_time' => time() - ( 35 * \DAY_IN_SECONDS ),
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
						'created_time' => time() - ( 35 * \DAY_IN_SECONDS ),
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
					'activated_time' => time() - ( 35 * \DAY_IN_SECONDS ),
				],
				[],
			],
		];
	}

	/**
	 * @dataProvider get_stale_inactive_versions_data
	 */
	public function test__get_stale_inactive_versions( $input_versions, $active_version, $expected_numbers ) {
		$versions = $input_versions;

		$indexable_mock = $this->getMockBuilder( \ElasticPress\Indexable::class )->getMock();

		$versioning_mock = $this->getMockBuilder( \Automattic\VIP\Search\Versioning::class )
			->setMethods( [ 'get_versions', 'get_active_version' ] )
			->getMock();
		$versioning_mock->method( 'get_versions' )->willReturn( $versions );
		$versioning_mock->method( 'get_active_version' )->willReturn( $active_version );
		$instance = new \Automattic\VIP\Search\VersioningCleanupJob( null, $versioning_mock );

		$result = $instance->get_stale_inactive_versions( $indexable_mock );

		$result_numbers = array_map( function( $element ) {
			return $element['number'];
		}, $result );

		$this->assertEquals( $expected_numbers, $result_numbers );
	}
}
