<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Pendo_Utils_Test extends WP_UnitTestCase {
	public function tear_down() {
		parent::tear_down();
		Constant_Mocker::clear();
	}

	public function test_get_base_properties_of_pendo_track_event(): void {
		wp_set_current_user( 1 );

		Constant_Mocker::define( 'WP_ENVIRONMENT_TYPE', 'test_for_fun' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$output = get_base_properties_of_pendo_track_event();

		$props = [
			'environment_type'   => 'test_for_fun',
			'hosting_provider'   => 'wpvip',
			'is_vip_user'        => false,
			'is_multisite'       => is_multisite(),
			'mu_plugins_version' => 'unknown',
			'wp_version'         => get_bloginfo( 'version' ),
		];
		$this->assertEquals( $props, $output );
	}

	public function test_get_base_properties_of_pendo_user(): void {
		wp_set_current_user( 1 );

		Constant_Mocker::define( 'VIP_ORG_ID', 11 );
		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', 'test_salt' );

		$output = get_base_properties_of_pendo_user();

		$props = [
			'account_id'     => 'nosfid_wordpress_11',
			'country_code'   => 'unknown',
			'org_id'         => '11',
			'role_wordpress' => 'administrator',
			'email'          => 'admin@example.org',
			'visitor_id'     => 'f492ac7d4b4e1b795d8ebe8a142d003fdac45e33490d47573a7b78a91a52bde9',
			'visitor_name'   => 'admin',
		];
		$this->assertEquals( $props, $output );
	}

	public function test_get_base_properties_of_user_with_no_role(): void {
		$user = $this->factory()->user->create_and_get( [
			'role'       => [],
			'user_login' => 'frances',
			'user_email' => 'frances@ha.com',
		] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_ORG_ID', 22 );
		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', 'test_salt' );

		$output = get_base_properties_of_pendo_user();

		$props = [
			'account_id'     => 'nosfid_wordpress_22',
			'country_code'   => 'unknown',
			'org_id'         => '22',
			'role_wordpress' => 'unknown',
			'email'          => 'frances@ha.com',
			'visitor_id'     => '2a69efbe98bed50d3fee619f409b5ded12fb63f1fab2dd52e211e2b626b49408',
			'visitor_name'   => 'frances',
		];
		$this->assertEquals( $props, $output );
	}
}
