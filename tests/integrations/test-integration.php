<?php
/**
 * Test: Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_private_method_as_public;
use function Automattic\Test\Utils\get_private_property_as_public;

require_once __DIR__ . '/fake-integration.php';

/**
 * Test Class.
 */
class VIP_Integration_Test extends WP_UnitTestCase {
	/**
	 * Test slug is setting up on integration instantiation.
	 */
	public function test__slug_is_setting_up_on_instantiation() {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
	}

	/**
	 * Test vip config is emtpy if config file does not exist.
	 */
	public function test__vip_config_is_empty_if_config_file_does_not_exist() {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( null );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [], $this->get_private_vip_config( $integration_mock ) );
	}

	/**
	 * Test vip config is emtpy if config file does not return configs in array.
	 */
	public function test__vip_config_is_empty_if_config_file_does_not_return_configs_in_array() {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( 'not-array' );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [], $this->get_private_vip_config( $integration_mock ) );
	}

	/**
	 * Test vip config is assigned if config file returns valid configs.
	 */
	public function test__vip_config_is_assigned_if_config_file_returns_valid_configs() {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( [ 'configs-in-array' ] );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [ 'configs-in-array' ], $this->get_private_vip_config( $integration_mock ) );
	}

	/**
	 * Test set_is_active_by_vip method is getting called on instantiation.
	 */
	public function test__set_is_active_by_vip_is_getting_called_on_instantiation(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( [ 'configs-in-array' ] );
		$integration_mock->expects( $this->once() )->method( 'set_is_active_by_vip' );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );
	}

	/**
	 * Activate function.
	 *
	 * @return void
	 */
	public function test__activate_is_setting_up_the_plugins_config_and_marking_the_integration_as_customer_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config_test' ] );

		$this->assertTrue( get_private_property_as_public( FakeIntegration::class, 'is_active_by_customer' )->getValue( $integration ) );
		$this->assertEquals( [ 'config_test' ], $integration->get_config() );
	}

	/**
	 * Test is_active is returning false when integration isn't active.
	 *
	 * @return void
	 */
	public function test__is_active_is_returning_false_when_integration_is_not_active_by_any_mean(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertFalse( $integration->is_active() );
	}

	/**
	 * Test is_active is returning true when integration is activated by customer.
	 *
	 * @return void
	 */
	public function test__is_active_is_returning_true_when_integration_is_activated_by_customer(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate();

		$this->assertTrue( $integration->is_active() );
	}

	/**
	 * Test is_active is returning true when integration is activated by VIP.
	 *
	 * @return void
	 */
	public function test__is_active_is_returning_true_when_integration_is_activated_by_vip(): void {
		$integration = new FakeIntegration( 'fake' );

		get_private_property_as_public( Integration::class, 'is_active_by_vip' )->setValue( $integration, true );

		$this->assertTrue( $integration->is_active() );
	}

	/**
	 * Test is_active is returning true from customer if integration is enabled by both vip and customer.
	 */
	public function test__is_active_is_returning_true_from_customer_if_integration_is_enabled_by_both_vip_and_customer(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();

		get_private_property_as_public( Integration::class, 'is_active_by_customer' )->setValue( $integration_mock, true );
		$integration_mock->expects( $this->exactly( 0 ) )->method( 'get_is_active_by_vip' );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );
	}

	/**
	 * Get private property 'vip_config' from integration object.
	 *
	 * @param Integration $integration Object of the integration.
	 *
	 * @return mixed
	 */
	private function get_private_vip_config( $integration ) {
		return get_private_property_as_public( Integration::class, 'vip_config' )->getValue( $integration );
	}
}
