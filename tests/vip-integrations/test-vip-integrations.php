<?php
/**
 * Test: VIP Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use InvalidArgumentException;
use stdClass;

require_once __DIR__ . '/fake-integration.php';

/**
 * Test Class.
 */
class VIP_Integrations_Test extends WP_UnitTestCase {
	/**
	 * Test registering integration and then activating it is loading integration.
	 */
	public function test__register_integration_as_instantiated_class__loads_integration() {
		$integrations     = new Integrations();
		$fake_integration = new FakeIntegration( 'fake' );

		$integrations->register( $fake_integration );
		$integrations->activate( 'fake' );
		$integrations->load_active();

		$this->assertTrue( $fake_integration->is_loaded_instance );
	}

	/**
	 * Test registering integration without activation does not load integration.
	 */
	public function test__register_integration_without_activation__does_not_load_integration() {
		$integrations     = new Integrations();
		$fake_integration = new FakeIntegration( 'fake' );

		$integrations->register( $fake_integration );
		$integrations->load_active();

		$this->assertFalse( $fake_integration->is_loaded_instance );
	}

	/**
	 * Test registering same integration twice throws error.
	 */
	public function test__double_slug_registration__throws_invalidArgumentException() {
		$this->expectException( InvalidArgumentException::class );

		$integrations     = new Integrations();
		$fake_integration = new FakeIntegration( 'fake' );

		$integrations->register( $fake_integration );
		$integrations->register( $fake_integration );
	}

	/**
	 * Test registering integration which is not a subclass of Integration throws error.
	 */
	public function test__non_integration_subclass__throws_invalidArgumentException() {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$random_class = new stdClass();

		$integrations->register( $random_class );
	}

	/**
	 * Test activating integration on invalid slug throws error.
	 */
	public function test__activating_integration_by_passing_invalid_slug__throws_invalidArgumentException() {
		$this->expectException( InvalidArgumentException::class );

		$integrations     = new Integrations();
		$fake_integration = new FakeIntegration( 'fake' );

		$integrations->register( $fake_integration );
		$integrations->activate( 'invalid-slug' );
	}
}
