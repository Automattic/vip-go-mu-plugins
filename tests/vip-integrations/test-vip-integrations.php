<?php

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

require_once __DIR__ . '/fake-integration.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class VIP_Integrations_Test extends WP_UnitTestCase {
	public function setUp(): void {
		// Remove existing default integrations to avoid conflicts when re-registering
		Integrations::instance()->remove_registered();
	}

	public function test_register_integration_as_class_name() {
		add_action( 'vip_integrations_register', function() {
			Integrations::instance()->register( 'fake-integration', FakeIntegration::class );
		});

		activate( 'fake-integration' );

		$this->run_integration_actions();
		$this->assertTrue( FakeIntegration::$is_loaded );
	}

	public function test_register_integration_as_instantiated_class() {
		$fake_integration = new class() extends Integration {
			public $is_loaded = false;

			public function load( array $config ): void {
				$this->is_loaded = true;
			}
		};

		add_action( 'vip_integrations_register', function() use ( $fake_integration ) {
			Integrations::instance()->register( 'fake-integration', $fake_integration );
		});

		activate( 'fake-integration' );

		$this->run_integration_actions();
		$this->assertTrue( $fake_integration->is_loaded );
	}

	private function run_integration_actions() {
		do_action( 'vip_integrations_register' );
		do_action( 'vip_integrations_activate' );
		do_action( 'vip_integrations_load' );
	}
}
