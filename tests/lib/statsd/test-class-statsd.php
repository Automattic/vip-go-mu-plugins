<?php
namespace Automattic\VIP;

use \WP_Query;

class StatsD_Test extends \WP_UnitTestCase {

	const STATS_KEY = "stats.example";

	public function setUp() {
		require_once __DIR__ . '/../../../lib/statsd/class-statsd.php';
	}

	public function test__time__calls_timed() {
		$taskDurationMillis = 1;
		$taskDurationNanos = $taskDurationMillis * 1000 * 1000;

		$partiallyMockedStatsD = $this->getMockBuilder( StatsD::class )
			->setMethods( [ 'timing' ] )
			->getMock();


		$partiallyMockedStatsD->expects( $this->once() )
			->method( 'timing' )
			->with(
				$this->STATS_KEY,
				$this->greaterThan( $taskDurationMillis )
			);

		$partiallyMockedStatsD->time(
			$this->STATS_KEY,
			function () use ( $taskDurationNanos ) {
				time_nanosleep( 0, $taskDurationNanos );
			}
		);
	}

}
