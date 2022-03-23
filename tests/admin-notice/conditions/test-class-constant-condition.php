<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../../admin-notice/conditions/interface-condition.php';
require_once __DIR__ . '/../../../admin-notice/conditions/class-constant-condition.php';

class Constant_Condition_Test extends \PHPUnit\Framework\TestCase {

	public function evaluate_data() {
		return [
			[ $this->build_condition( 'TEST_CONSTANT', true ), true ],
			[ $this->build_condition( 'FOO', 'bar' ), true ],
			[ $this->build_condition( 'TESTING', 123, false ), false ],
		];
	}

	/**
	 * @dataProvider evaluate_data
	 */
	public function test__evaluate( $condition, $expected_result ) {
		$this->assertEquals( $expected_result, $condition->evaluate() );
	}

	private function build_condition( string $constant, $value, $defined = true ) {
		if ( $defined ) {
			define( $constant, $value );
		}

		return new Constant_Condition(
			$constant,
			$value
		);
	}
}
