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

use function Automattic\Test\Utils\get_private_property_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	public function test__load_active_loads_the_activated_integration(): void {
		$integrations = new Integrations();

		$integration_1 = new FakeIntegration( 'fake-1' );
		$integrations->register( $integration_1 );
		$integrations->activate( 'fake-1' );
		$integrations->load_active();

		$this->assertTrue( $this->get_is_active_by_customer( $integration_1 ) );
	}

	public function test__load_active_does_not_loads_the_non_activated_integration(): void {
		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->load_active();

		$this->assertFalse( $this->get_is_active_by_customer( $integration ) );
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

	/**
	 * Get private property 'is_active_by_customer' from integration object.
	 *
	 * @param Integration $integration Object of the integration.
	 */
	private function get_is_active_by_customer( $integration ): bool {
		return get_private_property_as_public( Integration::class, 'is_active_by_customer' )->getValue( $integration );
	}
}
