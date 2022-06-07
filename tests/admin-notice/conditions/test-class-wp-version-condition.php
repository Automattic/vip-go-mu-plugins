<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../../admin-notice/conditions/interface-condition.php';
require_once __DIR__ . '/../../../admin-notice/conditions/class-wp-version-condition.php';

class WP_Version_Condition_Test extends \PHPUnit\Framework\TestCase {

	public function evaluate_data() {

		return [
			[ '5.3', '5.6', '5.4.2', true ],
			[ '5.3', '5.6', '5.7', false ],
			[ '5.3', '5.6', '5.1', false ],
			[ '5.3', '5.6', '5.3', true ],
			[ '5.3', '5.6', '5.6', false ],
			[ null, null, '5.6', true ],
			[ '5.5', null, '5.6', true ],
			[ null, '5.7', '5.6', true ],
			[ '5.7', null, '5.6', false ],
			[ null, '5.4', '5.6', false ],
		];
	}

	/**
	 * @dataProvider evaluate_data
	 */
	public function test__evaluate( $min, $max, $current, $expected_result ) {
		$orig_wp_version = $GLOBALS['wp_version'];
		try {
			$GLOBALS['wp_version'] = $current;

			$condition = new WP_Version_Condition( $min, $max );

			$this->assertEquals( $expected_result, $condition->evaluate() );
		} finally {
			$GLOBALS['wp_version'] = $orig_wp_version;
		}
	}
}

