<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../admin-notice/class-admin-notice-controller.php';
require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';

class Admin_Notice_Controller_Test extends \WP_UnitTestCase {

	public function test__init__should_attach_filter() {
		$controller = new Admin_Notice_Controller();

		remove_all_filters( 'admin_notices' );

		$controller->init();

		$this->assertTrue( has_filter( 'admin_notices' ) );
	}


	public function filter_notices_by_time_data() {

		return [
			[ [ $this->build_admin_notice( 'a', '-1 day', '+1 day' ) ], [ 'a' ] ],
			[ [ $this->build_admin_notice( 'a', '+1 day', '-1 day' ) ], [] ],
			[ [ $this->build_admin_notice( 'a', '+1 day', '+1 day' ) ], [] ],
			[ [ $this->build_admin_notice( 'a', '-3 hours', '-1 hour' ), $this->build_admin_notice( 'b', '-3 hours', '+1 hour' ) ], [ 'b' ] ],
		];
	}

	/**
	 * @dataProvider filter_notices_by_time_data
	 */
	public function test__filter_notices_by_time( $input_notices, $expected_messages ) {
		$controller = new Admin_Notice_Controller();

		$result = $controller->filter_notices_by_time( $input_notices );

		$messages = array_map( function ( $notice ) {
			return $notice->message;
		}, $result );

		$this->assertEmpty( array_diff( $expected_messages, $messages ) );
	}

	public function test__convert_notice_to_html() {
		$controller = new Admin_Notice_Controller();
		$message = 'Test Message';
		$expected_html = "<div class=\"notice notice-info\"><p>$message</p></div>";
		$notice = new Admin_Notice( $message, '01-01-2020', '01-01-2020' );

		$html = $controller->convert_notice_to_html( $notice );

		$this->assertEquals( $expected_html, $html );
	}

	private function build_admin_notice( $message, $start_shift, $end_shift ) {
		$start_date = date_modify( new \DateTime(), $start_shift );
		$end_date = date_modify( new \DateTime(), $end_shift );
		$format_string = 'd-m-Y H:i';

		return new Admin_Notice(
			$message,
			$start_date->format( $format_string ),
			$end_date->format( $format_string )
		);
	}
}
