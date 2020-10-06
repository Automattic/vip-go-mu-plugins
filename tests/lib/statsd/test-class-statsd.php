<?php
namespace Automattic\VIP;

use \WP_Query;

class StatsD_Test extends \WP_UnitTestCase {

	const STATS_KEY = 'stats.example';

	public function setUp() {
		require_once __DIR__ . '/../../../lib/statsd/class-statsd.php';
	}

	public function test__time__calls_timed() {
		$task_duration_millis = 1;
		$task_duration_nanos = $task_duration_millis * 1000 * 1000;

		$partially_mocked_statsd = $this->getMockBuilder( StatsD::class )
			->setMethods( [ 'timing' ] )
			->getMock();


		$partially_mocked_statsd->expects( $this->once() )
			->method( 'timing' )
			->with(
				self::STATS_KEY,
				$this->greaterThan( $task_duration_millis )
			);

		$partially_mocked_statsd->time(
			self::STATS_KEY,
			function () use ( $task_duration_nanos ) {
				time_nanosleep( 0, $task_duration_nanos );
			}
		);
	}

}
