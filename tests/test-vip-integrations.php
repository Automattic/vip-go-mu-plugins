<?php
/**
 * Test: VIP Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class VIP_Integrations_Plugin_Test extends WP_UnitTestCase {
	public function test__availability_of_supported_integrations(): void {
		/**
		 * Test supported integrations.
		 *
		 * @var array<Integration> $supported_vip_integrations
		 */
		global $supported_vip_integrations;

		$this->assertEquals( 2, count( $supported_vip_integrations ) );
		$this->assertInstanceOf( BlockDataApiIntegration::class, $supported_vip_integrations[0] );
		$this->assertInstanceOf( ParselyIntegration::class, $supported_vip_integrations[1] );
	}

	public function test__all_supported_integrations_are_registered(): void {
		/**
		 * VIP Integrations.
		 *
		 * @var Integrations $vip_integrations
		 */
		global $vip_integrations;
		$get_method = get_class_method_as_public( $vip_integrations, 'get' );

		$parsely_integration = $get_method->invoke( $vip_integrations, 'block-data-api' );
		$this->assertNotNull( $parsely_integration );

		$parsely_integration = $get_method->invoke( $vip_integrations, 'parsely' );
		$this->assertNotNull( $parsely_integration );

		$not_supported_integration = $get_method->invoke( $vip_integrations, 'non-supported' );
		$this->assertNull( $not_supported_integration );
	}

	public function test_activate_function_is_calling_the_activate_method_from_integrations_class(): void {
		$mock = $this->getMockBuilder( Integrations::class )->getMock();
		$mock->expects( $this->once() )->method( 'activate' )->with( $this->equalTo( 'test-slug' ), $this->equalTo( [ 'test-key' => 'test-value' ] ) );

		global $vip_integrations;
		$temp             = $vip_integrations; // Backup vip integrations.
		$vip_integrations = $mock;

		activate( 'test-slug', [ 'test-key' => 'test-value' ] );

		$vip_integrations = $temp; // Reset vip integrations from backup.
	}

	public function test_activated_integrations_are_loaded_on_muplugins_loaded_hook(): void {
		$mock = $this->getMockBuilder( Integrations::class )->getMock();
		$mock->expects( $this->once() )->method( 'load_active' )->with();

		global $vip_integrations;
		$temp             = $vip_integrations; // Backup vip integrations.
		$vip_integrations = $mock;

		do_action( 'muplugins_loaded' );

		$vip_integrations = $temp; // Reset vip integrations from backup.
	}
}
