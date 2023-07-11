<?php
/**
 * Test: Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_private_property_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Test extends WP_UnitTestCase {
	public function test__slug_is_setting_up_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
	}

	public function test__activate_is_setting_up_the_plugins_config_and_marking_the_integration_as_active_by_customer(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config_test' ] );

		$this->assertTrue( get_private_property_as_public( FakeIntegration::class, 'is_active_by_customer' )->getValue( $integration ) );
		$this->assertEquals( [ 'config_test' ], $integration->get_customer_config() );
	}

	public function test__is_active_returns_false_when_integration_is_not_active_by_any_option(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertFalse( $integration->is_active() );
	}

	public function test__is_active_returns_true_when_integration_is_activated_by_customer(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__is_active_returns_true_when_integration_is_activated_by_vip(): void {
		/**
		 * Mock object.
		 *
		 * @var FakeIntegration&MockObject
		 */
		$mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->setMethods( [ 'is_active_by_vip' ] )->getMock();
		$mock->expects( $this->once() )->method( 'is_active_by_vip' )->willReturn( true );

		$this->assertTrue( $mock->is_active() );
	}

	public function test__is_active_returns_true_from_customer_if_integration_is_enabled_by_both_vip_and_customer(): void {
		/**
		 * Mock object.
		 *
		 * @var FakeIntegration&MockObject
		 */
		$mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->setMethods( [ 'is_active_by_vip' ] )->getMock();
		get_private_property_as_public( Integration::class, 'is_active_by_customer' )->setValue( $mock, true );
		$mock->expects( $this->exactly( 0 ) )->method( 'is_active_by_vip' );

		$this->assertTrue( $mock->is_active() );
	}

	public function test__is_active_by_vip_returns_false_if_empty_config_is_provided(): void {
		$this->test_is_active_by_vip( [], false, false );
	}

	public function test__is_active_by_vip_returns_false_if_provided_config_is_not_of_type_array(): void {
		$this->test_is_active_by_vip( 'invalid-config', false, false );
	}

	public function test__is_active_by_vip_is_return_false_if_client_status_is_blocked(): void {
		$this->test_is_active_by_vip( [ 'client' => [ 'status' => Client_Integration_Status::BLOCKED ] ], false, false );
	}

	public function test__is_active_by_vip_returns_false_if_site_status_is_blocked(): void {
		$this->test_is_active_by_vip( [ 'site' => [ 'status' => Client_Integration_Status::BLOCKED ] ], false, false );
	}

	public function test__is_active_by_vip_returns_false_if_integration_is_blocked_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip( [ 'network_sites' => [ '1' => [ 'status' => Site_Integration_Status::BLOCKED ] ] ], false, false );
	}

	public function test__is_active_by_vip_returns_false_if_integration_is_disabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip( [ 'network_sites' => [ '1' => [ 'status' => Site_Integration_Status::BLOCKED ] ] ], false, false );
	}

	public function test__is_active_by_vip_returns_true_if_integration_is_enabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip( [
			'network_sites' => [
				'1' => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => [ 'network sites config' ],
				],
			],
		], true, true );

		$setup_config_value = apply_filters( 'fake_vip_config_filter', '' );
		$this->assertEquals( [ 'network sites config' ], $setup_config_value );
	}

	public function test__is_active_by_vip_returns_true_without_adding_filter_if_integration_is_enabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip( [ 'network_sites' => [ '1' => [ 'status' => Site_Integration_Status::ENABLED ] ] ], true, false );
	}

	public function test__is_active_by_vip_returns_false_if_integration_config_is_not_provided_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip( [], false, false );
	}

	public function test__is_active_by_vip_returns_false_if_integration_is_disabled_on_site(): void {
		$this->test_is_active_by_vip( [ 'site' => [ 'status' => Site_Integration_Status::DISABLED ] ], false, false );
	}

	public function test__is_active_by_vip_returns_true_if_integration_is_enabled_on_site(): void {
		$this->test_is_active_by_vip( [
			'site' => [
				'status' => Site_Integration_Status::ENABLED,
				'config' => [ 'site config' ],
			],
		], true, true );

		$setup_config_value = apply_filters( 'fake_vip_config_filter', '' );
		$this->assertEquals( [ 'site config' ], $setup_config_value );
	}

	public function test__is_active_by_vip_returns_true_without_adding_filter_if_integration_is_enabled_on_site(): void {
		$this->test_is_active_by_vip( [ 'site' => [ 'status' => Site_Integration_Status::ENABLED ] ], true, false );
	}

	public function test__is_active_by_vip_returns_false_if_integration_config_is_not_provided_on_site(): void {
		$this->test_is_active_by_vip( [], false, false );
	}

	/**
	 * Helper function for testing `is_active_by_vip`.
	 *
	 * @param array|string $vip_config
	 * @param boolean      $expected_is_active_by_vip
	 * @param boolean      $expected_has_config_filter
	 *
	 * @return void
	 */
	private function test_is_active_by_vip(
		$vip_config,
		bool $expected_is_active_by_vip,
		bool $expected_has_config_filter
	) {
		/**
		 * Mock object.
		 *
		 * @var FakeIntegration&MockObject
		 */
		$mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->setMethods( [ 'get_vip_config_from_file' ] )->getMock();
		$mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( $vip_config );

		$is_active = $mock->is_active_by_vip();

		$this->assertEquals( $expected_is_active_by_vip, $is_active );
		$this->assertEquals( $expected_has_config_filter, has_filter( 'fake_vip_config_filter' ) );
	}

	public function test__get_vip_config_from_file_returns_null_if_config_file_does_not_exist(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertNull( $integration->get_vip_config_from_file() );
	}

	public function test__get_value_from_vip_config_throws_exception_if_invalid_argument_is_passed(): void {
		$this->expectException( InvalidArgumentException::class );
		$mocked_vip_configs = [];

		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'invalid-config-type', 'key', '' );
	}

	public function test__get_value_from_vip_config_returns_empty_string_if_given_config_type_have_no_data(): void {
		$mocked_vip_configs = [];

		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'client', 'status', '' );
	}

	public function test__get_value_from_vip_config_returns_config_from_client_data(): void {
		$mocked_vip_configs = [
			'client' => [
				'status' => Client_Integration_Status::BLOCKED,
				'config' => array( 'client_configs' ),
			],
		];

		// Test return of string value.
		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'client', 'status', Client_Integration_Status::BLOCKED );

		// Test return of array value.
		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'client', 'config', array( 'client_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_config_of_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$mocked_vip_configs = [
			'site'          => [
				'status' => Site_Integration_Status::BLOCKED,
				'config' => array( 'site_configs' ),
			],
			'network_sites' => [
				'1' => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => array( 'network_site_1_configs' ),
				],
				'2' => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => array( 'network_site_2_configs' ),
				],
			],
		];

		// Test return of string value.
		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'network_sites', 'status', Site_Integration_Status::ENABLED );

		// Test return of array value.
		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'network_sites', 'config', array( 'network_site_1_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_empty_string_if_non_existent_key_is_passed(): void {
		$mocked_vip_configs = [
			'site' => [
				'status' => Site_Integration_Status::BLOCKED,
				'config' => array( 'site_configs' ),
			],
		];

		$this->test_get_value_from_vip_config( $mocked_vip_configs, 'site', 'invalid_key', '' );
	}

	/**
	 * Helper function for testing get_value_from_vip_config.
	 *
	 * @param array        $vip_config
	 * @param string       $config_type
	 * @param string       $key
	 * @param string|array $expected_value_from_vip_config
	 *
	 * @return void
	 */
	private function test_get_value_from_vip_config(
		array $vip_config,
		string $config_type,
		string $key,
		$expected_value_from_vip_config
	): void {
		$integration = new FakeIntegration( 'fake' );
		get_private_property_as_public( Integration::class, 'vip_config' )->setValue( $integration, $vip_config );

		$config_value = $integration->get_value_from_vip_config( $config_type, $key );

		$this->assertEquals( $expected_value_from_vip_config, $config_value );
	}
}
