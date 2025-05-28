<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Telemetry\Pendo\Pendo_JavaScript_Library;
use WP_UnitTestCase;
use function get_bloginfo;
use function wp_scripts;
use function wp_set_current_user;

class Pendo_JavaScript_Library_Test extends WP_UnitTestCase {
	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_enabled_for_users_with_edit_post_cap() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertTrue( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_enabled_for_users_to_allowed_admin_screens() {
		global $wp_query;

		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		$wp_query->query_vars['page'] = 'vip-block-governance';

		$this->assertTrue( Pendo_JavaScript_Library::should_enqueue_script( 'admin.php' ) );
	}

	public function test_enabled_for_filter_allowed_screens() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$allow_screen = function ( $screens ) {
			$screens[] = 'newly-allowed-screen.php';
			return $screens;
		};

		add_filter( 'vip_pendo_allowed_screens', $allow_screen );
		$this->assertTrue( Pendo_JavaScript_Library::should_enqueue_script( 'newly-allowed-screen.php' ) );
		remove_filter( 'vip_pendo_allowed_screens', $allow_screen );
	}

	public function test_enabled_for_filter_allowed_admin_screens() {
		global $wp_query;

		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		$wp_query->query_vars['page'] = 'admin-page-slug';

		$allow_admin_screen = function ( $admin_screens ) {
			$admin_screens[] = 'admin-page-slug';
			return $admin_screens;
		};

		add_filter( 'vip_pendo_allowed_admin_screens', $allow_admin_screen );
		$this->assertTrue( Pendo_JavaScript_Library::should_enqueue_script( 'admin.php' ) );
		remove_filter( 'vip_pendo_allowed_admin_screens', $allow_admin_screen );
	}

	public function test_disabled_by_opt_out_constant() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_DISABLE_PENDO_TELEMETRY', true );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_non_vip_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_non_production_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'preprod' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_fedramp_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_sandbox_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_users_without_edit_post_cap() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_disallowed_screens() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'non-allowed-screen.php' ) );
	}

	public function test_disabled_for_disallowed_admin_screen() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'admin.php' ) );
	}

	public function test_should_return_singleton_instance() {
		$instance = Pendo_JavaScript_Library::init( 'test_api_key' );

		$this->assertSame( $instance, Pendo_JavaScript_Library::init( 'test_api_key' ) );
		$this->assertSame( $instance, Pendo_JavaScript_Library::init( 'test_api_key' ) );
	}

	public function test_initialization_data() {
		$user = $this->factory()->user->create_and_get( [
			'role'         => 'author',
			'display_name' => 'Frances Ha',
			'user_email'   => 'frances@ha.com',
		] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'VIP_ORG_ID', 555 );
		Constant_Mocker::define( 'VIP_SF_ACCOUNT_ID', 111 );
		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', 'test_salt' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$instance = Pendo_JavaScript_Library::init( 'test_api_key' );
		$instance->enqueue_scripts( 'index.php' );

		$registered_scripts = wp_scripts()->registered;
		$this->assertArrayHasKey( 'vip-pendo-agent-script', $registered_scripts );

		$expected_data = [
			'apiKey'    => 'test_api_key',
			'account'   => [
				'id'         => '111',
				'vip_org_id' => '555',
				'wp_version' => get_bloginfo( 'version' ),
			],
			'env'       => 'io',
			'globalKey' => 'VIP_PENDO_MU_PLUGINS',
			'plugins'   => [],
			'visitor'   => [
				'id'             => '2a69efbe98bed50d3fee619f409b5ded12fb63f1fab2dd52e211e2b626b49408',
				'country_code'   => 'unknown',
				'full_name'      => 'Frances Ha',
				'role_wordpress' => 'author',
			],
		];

		$serialized_data = sprintf( 'var %s = %s;', 'VIP_PENDO_MU_PLUGINS_INIT_DATA', wp_json_encode( $expected_data ) );

		$this->assertSame( $serialized_data, $registered_scripts['vip-pendo-agent-script']->extra['data'] );
	}
}
