<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';
require_once __DIR__ . '/../../admin-notice/conditions/interface-condition.php';

class Admin_Notice_Test extends \WP_UnitTestCase {

	public function test__to_html() {
		$message = 'Test Message';
		$expected_html = "<div class=\"notice notice-info\"><p>$message</p></div>";
		$notice = new Admin_Notice( $message );

		$html = $notice->to_html();

		$this->assertEquals( $expected_html, $html );
	}

	public function should_render_data() {

		return [
			[ [], true ],
			[ [ false ], false ],
			[ [ true ], true ],
			[ [ true, true ], true ],
			[ [ true, false ], false ],
		];
	}

	/**
	 * @dataProvider should_render_data
	 */
	public function test__should_render( $condition_results, $expected_result ) {

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
