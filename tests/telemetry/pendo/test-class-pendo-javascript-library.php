<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Telemetry\Pendo\Pendo_JavaScript_Library;
use WP_UnitTestCase;

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

		$this->assertTrue( Pendo_JavaScript_Library::should_enqueue_script() );
	}

	public function test_disabled_by_opt_out_constant() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_DISABLE_PENDO_TELEMETRY', true );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script() );
	}

	public function test_disabled_for_non_vip_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script() );
	}

	public function test_disabled_for_non_production_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'preprod' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script() );
	}

	public function test_disabled_for_fedramp_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script() );
	}

	public function test_disabled_for_sandbox_environments() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script() );
	}

	public function test_disabled_for_users_without_edit_post_cap() {
		$user = $this->factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script() );
	}
}
