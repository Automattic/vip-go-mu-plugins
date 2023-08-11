<?php
/**
 * Test: Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use LogicException;
use WP_UnitTestCase;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Test extends WP_UnitTestCase {
	public function test__slug_is_setting_up_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
	}

	public function test__activate_is_marking_the_integration_as_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__activate_is_setting_up_the_plugins_config(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config_test' ] );

		$this->assertEquals( [ 'config_test' ], $integration->get_config() );
	}

	public function test__calling_active_twice_on_same_integration_does_not_activate_the_plugin_second_time(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( array( 'configs-1' ) );
		$integration->activate( array( 'configs-2' ) );

		$this->assertEquals( array( 'configs-1' ), $integration->get_config() );
	}

	public function test__is_active_returns_false_when_integration_is_not_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertFalse( $integration->is_active() );
	}
}
