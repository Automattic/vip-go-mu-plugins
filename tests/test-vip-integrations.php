<?php
/**
 * Test: VIP Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use stdClass;
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

	public function test_wpvip_is_integration_enabled_delegates_to_integrations_instance(): void {
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'is_integration_enabled' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'is_integration_enabled' )->with( 'test-slug' )->willReturn( true );

		$this->set_integrations( $integrations_mock );

		$result = wpvip_is_integration_enabled( 'test-slug' );

		$this->assertTrue( $result );
	}

	public function test_wpvip_get_integration_delegates_to_integrations_instance(): void {
		$mock_integration  = $this->getMockBuilder( Integration::class )->disableOriginalConstructor()->getMock();
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_integration' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'get_integration' )->with( 'test-slug' )->willReturn( $mock_integration );

		$this->set_integrations( $integrations_mock );

		$result = wpvip_get_integration( 'test-slug' );

		$this->assertSame( $mock_integration, $result );
	}

	public function test_wpvip_get_integration_info_delegates_to_integrations_instance(): void {
		$mock_info         = [
			'slug'      => 'test-slug',
			'is_active' => true,
		];
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_integration_info' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'get_integration_info' )->with( 'test-slug' )->willReturn( $mock_info );

		$this->set_integrations( $integrations_mock );

		$result = wpvip_get_integration_info( 'test-slug' );

		$this->assertSame( $mock_info, $result );
	}

	public function test_wpvip_get_enabled_integrations_delegates_to_integrations_instance(): void {
		$mock_integrations = [ 'test-slug' => new stdClass() ];
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_enabled_integrations' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'get_enabled_integrations' )->willReturn( $mock_integrations );

		$this->set_integrations( $integrations_mock );

		$result = wpvip_get_enabled_integrations();

		$this->assertSame( $mock_integrations, $result );
	}

	public function test_wpvip_get_all_integrations_delegates_to_integrations_instance(): void {
		$mock_integrations = [ 'test-slug' => new stdClass() ];
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_all_integrations' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'get_all_integrations' )->willReturn( $mock_integrations );

		$this->set_integrations( $integrations_mock );

		$result = wpvip_get_all_integrations();

		$this->assertSame( $mock_integrations, $result );
	}

	public function test_wpvip_get_integrations_summary_delegates_to_integrations_instance(): void {
		$mock_summary      = [ 'test-slug' => [ 'is_active' => true ] ];
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_integrations_summary' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'get_integrations_summary' )->willReturn( $mock_summary );

		$this->set_integrations( $integrations_mock );

		$result = wpvip_get_integrations_summary();

		$this->assertSame( $mock_summary, $result );
	}
}
