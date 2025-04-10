<?php

namespace Automattic\VIP\Prometheus;

use WP_UnitTestCase;
use Two_Factor_Core;

require_once __DIR__ . '/../../prometheus-collectors/class-user-stats-collector.php';

class Test_User_Stats_Collector extends WP_UnitTestCase {
	public User_Stats_Collector $collector;

	public function setUp(): void {
		parent::setUp();

		$this->collector = new User_Stats_Collector();
	}

	public function test_default_counts(): void {
		$user_count = [
			'avail_roles' => [
				'administrator' => 5,
				'subscriber'    => 10,
			],
		];
		$expected   = [
			'administrator' => [
				'disabled' => 0,
				'enabled'  => 0,
				'unknown'  => 5,
			],
			'subscriber'    => [
				'disabled' => 0,
				'enabled'  => 0,
				'unknown'  => 10,
			],
		];
		$result     = $this->collector->enrich_user_count( $user_count );
		$this->assertEquals( $expected, $result );
	}

	public function test_2fa_counts(): void {

		// Create users and set 2FA meta
		$admin_user      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$subscriber_user = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$meta_key   = Two_Factor_Core::ENABLED_PROVIDERS_USER_META_KEY;
		$meta_value = array( 'Two_Factor_Email' );
		update_user_meta( $admin_user, $meta_key, $meta_value );
		update_user_meta( $subscriber_user, $meta_key, $meta_value );

		$user_count = [
			'avail_roles' => [
				'administrator' => 5,
				'editor'        => 5,
				'subscriber'    => 10,
			],
		];

		$expected = [
			'administrator' => [
				'disabled' => 4,
				'enabled'  => 1,
				'unknown'  => 0,
			],
			'editor'        => [
				'disabled' => 5,
				'enabled'  => 0,
				'unknown'  => 0,
			],
			'subscriber'    => [
				'disabled' => 9,
				'enabled'  => 1,
				'unknown'  => 0,
			],
		];

		$result = $this->collector->enrich_user_count_with_2fa( $user_count, $this->collector::TFA_STATUS_DISABLED );
		$this->assertEquals( $expected, $result );
	}
}
