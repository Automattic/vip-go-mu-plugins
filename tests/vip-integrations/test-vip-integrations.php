<?php

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use InvalidArgumentException;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		// Reset static is_loaded status on integration so it can be reloaded
		FakeIntegration::$is_loaded = false;
	}

	// Registration

	public function test_register_integration_as_class_name_loads() {
		$integrations = new Integrations();
		$integrations->register( 'fake-integration-class', FakeIntegration::class );

		$integrations->activate( 'fake-integration-class' );

		$integrations->load_active();
		$this->assertTrue( FakeIntegration::$is_loaded );
	}

	public function test_register_integration_as_instantiated_class_loads() {
		$fake_integration = new class() extends Integration {
			public $is_loaded = false;

			public function load( array $config ): void {
				$this->is_loaded = true;
			}
		};

		$integrations = new Integrations();
		$integrations->register( 'fake-integration-instance', $fake_integration );

		$integrations->activate( 'fake-integration-instance' );

		$integrations->load_active();
		$this->assertTrue( $fake_integration->is_loaded );
	}

	// Non-activation

	public function test_register_integration_without_activation_does_not_load() {
		$integrations = new Integrations();
		$integrations->register( 'fake-integration', FakeIntegration::class );

		$integrations->load_active();
		$this->assertFalse( FakeIntegration::$is_loaded );
	}

	// Errors

	public function test_double_slug_registration_throws() {
		$this->expectException( InvalidArgumentException::class );

		$an_integration = new class() extends Integration {
			public function load( array $config ): void {}
		};

		$integrations = new Integrations();
		$integrations->register( 'non-unique-slug', FakeIntegration::class );
		$integrations->register( 'non-unique-slug', $an_integration );
	}

	public function test_invalid_class_name_throws() {
		$this->expectException( InvalidArgumentException::class );

		$integrations = new Integrations();
		$integrations->register( 'fake-integration', FakeIntegration::class . '-does-not-exist' );
	}

	public function test_non_integration_subclass_throws() {
		$this->expectException( InvalidArgumentException::class );

		$random_class = new class() {};

		$integrations = new Integrations();
		$integrations->register( 'fake-integration', $random_class );
	}
}
