<?php
/**
 * Test: VIP Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class VIP_Integrations_Plugin_Test extends WP_UnitTestCase {
	public function test_activate_function_is_calling_the_activate_method_from_integrations_class(): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'activate' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'activate' )->with( $this->equalTo( 'test-slug' ), $this->equalTo( [ 'test-key' => 'test-value' ] ) );

		$this->set_integrations( $integrations_mock );

		activate( 'test-slug', [ 'test-key' => 'test-value' ] );
	}

	public function test_integrations_are_activated_via_vip_config_on_muplugins_loaded_hook(): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'activate_platform_integrations' )->with();

		$this->set_integrations( $integrations_mock );

		do_action( 'muplugins_loaded' );
		ob_clean();
	}

	public function test_activated_integrations_are_loaded_on_muplugins_loaded_hook(): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'load_active' )->with();

		$this->set_integrations( $integrations_mock );

		do_action( 'muplugins_loaded' );
		ob_clean();
	}

	/**
	 * Set integrations mock.
	 *
	 * @param MockObject&Integrations $mock
	 */
	private function set_integrations( $mock ): void {
		$instance = IntegrationsSingleton::instance();
		get_class_property_as_public( IntegrationsSingleton::class, 'instance' )->setValue( $instance, $mock );
	}
}
