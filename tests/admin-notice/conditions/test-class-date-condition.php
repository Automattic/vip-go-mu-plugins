<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../../admin-notice/conditions/interface-condition.php';
require_once __DIR__ . '/../../../admin-notice/conditions/class-date-condition.php';

class Date_Condition_Test extends \PHPUnit\Framework\TestCase {

	public function evaluate_data() {

		return [
			[ $this->build_condition( '-1 day', '+1 day' ), true ],
			[ $this->build_condition( '+1 day', '-1 day' ), false ],
			[ $this->build_condition( '+1 day', '+1 day' ), false ],
		];
	}

	/**
	 * @dataProvider evaluate_data
	 */
	public function test__evaluate( $condition, $expected_result ) {
		$this->assertEquals( $expected_result, $condition->evaluate() );
	}

	private function build_condition( $start_shift, $end_shift ) {
		$start_date    = date_modify( new \DateTime(), $start_shift );
		$end_date      = date_modify( new \DateTime(), $end_shift );
		$format_string = 'd-m-Y H:i';

		return new Date_Condition(
			$start_date->format( $format_string ),
			$end_date->format( $format_string ),
			'now'
		);
	}
}
