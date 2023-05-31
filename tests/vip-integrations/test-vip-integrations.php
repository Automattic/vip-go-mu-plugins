<?php

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

require_once __DIR__ . '/fake-integration.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VIP_Integrations_Test extends WP_UnitTestCase {
	public function test_register_integration_as_class_name() {
		$integrations = new Integrations();
		$integrations->register( 'fake-integration-class', FakeIntegration::class );

		$integrations->activate( 'fake-integration-class' );

		$integrations->load_active();
		$this->assertTrue( FakeIntegration::$is_loaded );
	}

	public function test_register_integration_as_class_name_without_activation() {
		$integrations = new Integrations();
		$integrations->register( 'fake-integration', FakeIntegration::class );

		$integrations->load_active();
		$this->assertFalse( FakeIntegration::$is_loaded );
	}

	public function test_register_integration_as_instantiated_class() {
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
}
