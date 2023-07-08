<?php
/**
 * Test: Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_private_method;
use function Automattic\Test\Utils\get_private_property;

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

		get_private_method( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [], $this->get_private_vip_config( $integration_mock ) );
	}

	/**
	 * Test vip config is emtpy if config file does not return configs in array.
	 */
	public function test__vip_config_is_empty_if_config_file_does_not_return_configs_in_array() {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( 'not-array' );

		get_private_method( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [], $this->get_private_vip_config( $integration_mock ) );
	}

	/**
	 * Test vip config is assigned if config file returns valid configs.
	 */
	public function test__vip_config_is_assigned_if_config_file_returns_valid_configs() {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( [ 'configs-in-array' ] );

		get_private_method( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [ 'configs-in-array' ], $this->get_private_vip_config( $integration_mock ) );
	}

	/**
	 * Get private property 'vip_config' from integration object.
	 *
	 * @param Integration $integration Object of the integration.
	 *
	 * @return mixed
	 */
	private function get_private_vip_config( $integration ) {
		return get_private_property( Integration::class, 'vip_config' )->getValue( $integration );
	}
}
