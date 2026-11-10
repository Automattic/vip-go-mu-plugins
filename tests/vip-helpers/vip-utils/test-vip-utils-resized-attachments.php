<?php

class WPCOM_VIP_Get_Resized_Attachment_Url_Test extends WP_UnitTestCase {
	public function test__invalid_attachment() {
		$attachment_id = 99999999;

		$actual_url = wpcom_vip_get_resized_attachment_url( $attachment_id, 100, 101 );

		$this->assertFalse( $actual_url );
	}

	public function test__valid_attachment() {
		$attachment_id = $this->factory()->attachment->create_object( [
			'file' => 'image.jpg',
		] );

		$attachment_url = wp_get_attachment_url( $attachment_id );
		$this->assertIsString( $attachment_url );
		$this->assertNotEmpty( wp_parse_url( $attachment_url, PHP_URL_HOST ) );

		$actual_url = wpcom_vip_get_resized_attachment_url( $attachment_id, 100, 101 );

		$this->assertSame( $attachment_url . '?w=100&h=101', $actual_url );
	}
}
