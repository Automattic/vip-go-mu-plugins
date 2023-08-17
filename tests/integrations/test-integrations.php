<?php
/**
 * Test: Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use WP_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	public function test__integrations_are_activating_based_on_given_vip_config(): void {
		$config_mock = $this->getMockBuilder( IntegrationConfig::class )->disableOriginalConstructor()->setMethods( [ 'is_active_via_vip', 'get_site_config' ] )->getMock();
		$config_mock->expects( $this->exactly( 2 ) )->method( 'is_active_via_vip' )->willReturnOnConsecutiveCalls( true, false );
		$config_mock->expects( $this->exactly( 1 ) )->method( 'get_site_config' )->willReturnOnConsecutiveCalls( [ 'vip-configs' ] );

		/**
		 * Integrations mock.
		 *
		 * @var MockObject|Integrations
		 */
		$mock = $this->getMockBuilder( Integrations::class )->setMethods( [ 'get_integration_config' ] )->getMock();
		$mock->expects( $this->any() )->method( 'get_integration_config' )->willReturn( $config_mock );

		$integration_1 = new FakeIntegration( 'fake-1' );
		$integration_2 = new FakeIntegration( 'fake-2' );
		$integration_3 = new FakeIntegration( 'fake-3' );
		$mock->register( $integration_1 );
		$mock->register( $integration_2 );
		$mock->register( $integration_3 );
		$mock->activate( 'fake-1', [ 'customer-configs' ] );

		$mock->activate_platform_integrations();

		$this->assertTrue( $integration_1->is_active() );
		$this->assertEquals( [ 'customer-configs' ], $integration_1->get_config() );
		$this->assertTrue( $integration_2->is_active() );
		$this->assertEquals( [ 'vip-configs' ], $integration_2->get_config() );
		$this->assertFalse( $integration_3->is_active() );
	}

	public function test__get_integration_config_returns_instance_of_IntegrationConfig(): void {
		$integrations = new Integrations();

		$integration_config = get_class_method_as_public( Integrations::class, 'get_integration_config' )->invoke( $integrations, 'slug' );

		$this->assertInstanceOf( IntegrationConfig::class, $integration_config );
	}

	public function test__load_active_loads_the_activated_integration(): void {
		$integrations = new Integrations();

		$integration = new FakeIntegration( 'fake' );
		$integrations->register( $integration );
		$integrations->activate( 'fake' );
		$integrations->load_active();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__load_active_does_not_loads_the_non_activated_integration(): void {
		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->load_active();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__double_slug_registration_throws_invalidArgumentException(): void {
		$this->expectException( 'PHPUnit_Framework_Error_Warning' ); 
		$this->expectExceptionMessage( 'Integration with slug "fake" is already registered.' );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->register( $integration );
	}

	public function test__non_integration_subclass_throws_invalidArgumentException(): void {
		$this->expectException( 'PHPUnit_Framework_Error_Warning' ); 
		$this->expectExceptionMessage( 'Integration class "stdClass" must extend Automattic\VIP\Integrations\Integration.' );

		$integrations = new Integrations();
		$random_class = new stdClass();

		$integrations->register( $random_class );
	}

	public function test__activating_integration_by_passing_invalid_slug_throws_invalidArgumentException(): void {
		$this->expectException( 'PHPUnit_Framework_Error_Warning' ); 
		$this->expectExceptionMessage( 'VIP Integration with slug "invalid-slug" is not a registered integration.' );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->activate( 'invalid-slug' );
	}
}
