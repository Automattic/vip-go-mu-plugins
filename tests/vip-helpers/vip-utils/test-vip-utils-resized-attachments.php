<?php

class WPCOM_VIP_Get_Resized_Attachment_Url_Test extends WP_UnitTestCase {
	public function test__invalid_attachment() {
		$attachment_id = 99999999;

		$actual_url = wpcom_vip_get_resized_attachment_url( $attachment_id, 100, 101 );

		$this->assertFalse( $actual_url );
	}

	public function test__valid_attachment() {
		$expected_end_of_url = '/image.jpg?w=100&h=101';

		$attachment_id = $this->factory->attachment->create_object( [
			'file' => 'image.jpg',
		] );

		$actual_url = wpcom_vip_get_resized_attachment_url( $attachment_id, 100, 101 );

		$actual_end_of_url = substr( $actual_url, strrpos( $actual_url, '/' ) );

		$this->assertEquals( $expected_end_of_url, $actual_end_of_url );
	}
}
