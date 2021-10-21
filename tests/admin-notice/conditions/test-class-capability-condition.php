<?php

namespace Automattic\VIP\Admin_Notice;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../admin-notice/conditions/interface-condition.php';
require_once __DIR__ . '/../../../admin-notice/conditions/class-capability-condition.php';

class Capability_Condition_Test extends TestCase {

	public static $mock_global_functions;

	public function setUp(): void {
		self::$mock_global_functions = $this->getMockBuilder( self::class )
			->setMethods( [ 'mock_current_user_can' ] )
			->getMock();
	}

	public function mock_current_user_can( string $capability, ...$args ) {
	}

	public function evaluate_data() {

		return [
			[ [ true ], true ],
			[ [ true, true ], true ],
			[ [ false ], false ],
			[ [ false, false ], false ],
			[ [ false, true ], false ],
			[ [ true, false ], false ],
		];
	}

	/**
	 * @dataProvider evaluate_data
	 */
	public function test__evaluate( $has_capabilities, $expected_result ) {

		self::$mock_global_functions->method( 'mock_current_user_can' )
			->will( $this->onConsecutiveCalls( ...$has_capabilities ) );

		$condition = new Capability_Condition( ...$has_capabilities );

		$this->assertEquals( $expected_result, $condition->evaluate() );
	}
}


/**
 * Overwriting global function so that no real remote request is called
 */
function current_user_can( string $capability, ...$args ) {
	return is_null( Capability_Condition_Test::$mock_global_functions ) ? null : Capability_Condition_Test::$mock_global_functions->mock_current_user_can( $capability, $args );
}
