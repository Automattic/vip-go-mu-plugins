<?php

class VIP_Go_Security_Test extends WP_UnitTestCase {
	public function test__admin_username_restricted() {
		$this->factory->user->create( [
			'user_login' => 'admin',
			'user_email' => 'admin@example.com',
			'user_pass'   => 'secret1',
		] );

		$result = wp_authenticate( 'admin', 'secret1' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_username_restricted() {
		$this->factory->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'   => 'secret2',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_LOGIN, 'secret2' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_email_restricted() {
		$this->factory->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'   => 'secret3',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_EMAIL, 'secret3' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__other_username_not_restricted() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'   => 'secret4',
		] );

		$result = wp_authenticate( 'taylorswift', 'secret4' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test__other_email_not_restricted() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'   => 'secret5',
		] );

		$result = wp_authenticate( 'taylor@example.com', 'secret5' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}
}
