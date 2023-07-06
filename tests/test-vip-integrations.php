<?php
/**
 * Test: VIP Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_private_method;

/**
 * Test Class.
 */
class VIP_Integrations_Test extends WP_UnitTestCase {
	/**
	 * Test Block Data API integration is supported.
	 */
	public function test__block_data_api_integration_is_successfully_registered() {
		/**
		 * VIP Integrations.
		 *
		 * @var Integrations $vip_integrations
		 */
		global $vip_integrations;
		$private_get_method = get_private_method( $vip_integrations, 'get' );

		$parsely_integration = $private_get_method->invoke( $vip_integrations, 'block-data-api' );
		$this->assertNotNull( $parsely_integration );
	}

	/**
	 * Test Parse.ly integration is supported.
	 */
	public function test__parsely_integration_is_successfully_registered() {
		/**
		 * VIP Integrations.
		 *
		 * @var Integrations $vip_integrations
		 */
		global $vip_integrations;
		$private_get_method = get_private_method( $vip_integrations, 'get' );

		$parsely_integration = $private_get_method->invoke( $vip_integrations, 'parsely' );
		$this->assertNotNull( $parsely_integration );
	}

	/**
	 * Test activate function.
	 */
	public function test_activate_function_is_calling_the_activate_method_from_integrations() {
		$mock = $this->getMockBuilder( Integrations::class )->setMethods( [ 'activate' ] )->getMock();
		$mock->expects( $this->once() )->method( 'activate' )->with( $this->equalTo( 'test-slug' ), $this->equalTo( [ 'test-key' => 'test-value' ] ) );

		global $vip_integrations;
		$temp             = $vip_integrations; // Backup vip integrations.
		$vip_integrations = $mock;

		activate( 'test-slug', [ 'test-key' => 'test-value' ] );

		$vip_integrations = $temp; // Reset vip integrations from backup.
	}

	/**
	 * Test loading of activated plugins.
	 */
	public function test_activated_integrations_are_loaded_on_muplugins_loaded_hook() {
		$mock = $this->getMockBuilder( Integrations::class )->setMethods( [ 'load_active' ] )->getMock();
		$mock->expects( $this->once() )->method( 'load_active' )->with();

		global $vip_integrations;
		$temp             = $vip_integrations; // Backup vip integrations.
		$vip_integrations = $mock;

		do_action( 'muplugins_loaded' );

		$vip_integrations = $temp; // Reset vip integrations from backup.
	}

	/**
	 * Test non supported integration
	 */
	public function test__non_supported_integration_is_not_successfully_registered() {
		/**
		 * VIP Integrations.
		 *
		 * @var Integrations $vip_integrations
		 */
		global $vip_integrations;
		$private_get_method = get_private_method( $vip_integrations, 'get' );

		$parsely_integration = $private_get_method->invoke( $vip_integrations, 'non-supported' );
		$this->assertNull( $parsely_integration );
	}
}
