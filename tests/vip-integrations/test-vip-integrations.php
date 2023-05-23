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
		add_action( 'register_vip_integrations', function() {
			Integrations::instance()->register( 'fake-integration', FakeIntegration::class );
		});

		activate( 'fake-integration' );

		$this->run_integration_actions();
		$this->assertTrue( FakeIntegration::$is_integrated );
	}

	public function test_register_integration_as_instantiated_class() {
		$fake_integration = new class() extends Integration {
			public $is_integrated = false;

			public function integrate( array $config ): void {
				$this->is_integrated = true;
			}
		};

		add_action( 'register_vip_integrations', function() use ( $fake_integration ) {
			Integrations::instance()->register( 'fake-integration', $fake_integration );
		});

		activate( 'fake-integration' );

		$this->run_integration_actions();
		$this->assertTrue( $fake_integration->is_integrated );
	}

	private function run_integration_actions() {
		do_action( 'register_vip_integrations' );
		do_action( 'activate_vip_integrations' );
		do_action( 'integrate_vip_integrations' );
	}
}
