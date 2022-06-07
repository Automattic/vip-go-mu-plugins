<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';
require_once __DIR__ . '/../../admin-notice/conditions/interface-condition.php';

class Admin_Notice_Class_Test extends \PHPUnit\Framework\TestCase {

	public function test__display() {
		$message = 'Test Message';
		$notice  = new Admin_Notice( $message );

		$expected_html = "#<div data-vip-admin-notice=\"\" class=\"notice notice-info vip-notice\"><p>$message</p></div>#";
		$this->expectOutputRegex( $expected_html );

		$notice->display();
	}

	public function test__display_dismissible() {
		$message    = 'Test Message';
		$dismiss_id = 'dismiss_id';
		$notice     = new Admin_Notice( $message, [], $dismiss_id );

		$expected_html = "#<div data-vip-admin-notice=\"$dismiss_id\" class=\"notice notice-info vip-notice is-dismissible\"><p>$message</p></div>#";
		$this->expectOutputRegex( $expected_html );

		$notice->display();
	}

	public function should_render_conditions_data() {

		return [
			[ [], true ],
			[ [ false ], false ],
			[ [ true ], true ],
			[ [ true, true ], true ],
			[ [ true, false ], false ],
			[ [ false, true ], false ],
			[ [ false, false ], false ],
		];
	}

	/**
	 * @dataProvider should_render_conditions_data
	 */
	public function test__should_render_conditions( $condition_results, $expected_result ) {

		$conditions = array_map( function ( $result_to_return ) {
			$condition_stub = $this->createMock( Condition::class );
			$condition_stub->method( 'evaluate' )->willReturn( $result_to_return );
			return $condition_stub;
		}, $condition_results);

		$notice = new Admin_Notice( 'foo', $conditions );

		$result = $notice->should_render();

		$this->assertEquals( $expected_result, $result );
	}
}
