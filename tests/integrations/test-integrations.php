<?php
/**
 * Test: Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use WP_UnitTestCase;
use InvalidArgumentException;
use stdClass;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	public function test__load_active_loads_the_activated_integration(): void {
		$integrations = new Integrations();

		$integration = new FakeIntegration( 'fake' );
		$integrations->register( $integration );
		$integrations->activate( 'fake' );
		$integrations->load_active();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__load_active_does_not_loads_the_non_activated_integration(): void {
		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->load_active();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__double_slug_registration_throws_invalidArgumentException(): void {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->register( $integration );
	}

	public function test__non_integration_subclass_throws_invalidArgumentException(): void {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$random_class = new stdClass();

		$integrations->register( $random_class );
	}

	public function test__activating_integration_by_passing_invalid_slug_throws_invalidArgumentException(): void {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->activate( 'invalid-slug' );
	}
}
