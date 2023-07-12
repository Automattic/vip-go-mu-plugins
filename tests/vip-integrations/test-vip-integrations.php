<?php

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use InvalidArgumentException;
use stdClass;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	// Registration

	public function test__register_integration_with_class_name__loads_integration() {
		// Reset class flag to ensure previous tests don't affect class registration
		FakeIntegration::$is_loaded_class = false;

		$integrations = new Integrations();
		$integrations->register( 'fake-integration-class', FakeIntegration::class );

		// Only activated integrations are loaded by the collection class, so activate FakeIntegration.
		$integrations->activate( 'fake-integration-class' );

		$integrations->load_active();
		$this->assertTrue( FakeIntegration::$is_loaded_class );
	}

	public function test__register_integration_as_instantiated_class__loads_integration() {
		$integrations     = new Integrations();
		$fake_integration = new FakeIntegration();
		$integrations->register( 'fake-integration-instance', $fake_integration );

		$integrations->activate( 'fake-integration-instance' );

		$integrations->load_active();
		$this->assertTrue( $fake_integration->is_loaded_instance );
	}

	// Non-activation

	public function test__register_integration_without_activation__does_not_load_integration() {
		$integrations     = new Integrations();
		$fake_integration = new FakeIntegration();
		$integrations->register( 'fake-integration', $fake_integration );

		$integrations->load_active();
		$this->assertFalse( $fake_integration->is_loaded_instance );
	}

	// Errors

	public function test__double_slug_registration__throws_invalidArgumentException() {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$integrations->register( 'non-unique-slug', FakeIntegration::class );
		$integrations->register( 'non-unique-slug', FakeIntegration::class );
	}

	public function test__invalid_class_name__throws_invalidArgumentException() {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$integrations->register( 'fake-integration', 'not-an-integration-class' );
	}

	public function test__non_integration_subclass__throws_invalidArgumentException() {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$random_class = new stdClass();
		$integrations->register( 'fake-integration', $random_class );
	}
}
