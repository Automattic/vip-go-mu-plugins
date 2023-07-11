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

use function Automattic\Test\Utils\get_private_method_as_public;
use function Automattic\Test\Utils\get_private_property_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Test extends WP_UnitTestCase {
	public function test__slug_is_setting_up_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
	}

	public function test__vip_config_is_empty_if_config_file_does_not_exist(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( null );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [], $this->get_private_vip_config( $integration_mock ) );
	}

	public function test__vip_config_is_empty_if_config_file_does_not_return_configs_in_array(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( 'not-array' );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [], $this->get_private_vip_config( $integration_mock ) );
	}

	public function test__vip_config_is_assigned_if_config_file_returns_valid_configs(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( [ 'configs-in-array' ] );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );

		$this->assertEquals( [ 'configs-in-array' ], $this->get_private_vip_config( $integration_mock ) );
	}

	private function get_private_vip_config( MockObject $integration ) {
		return get_private_property_as_public( Integration::class, 'vip_config' )->getValue( $integration );
	}

	public function test__set_is_active_by_vip_is_getting_called_on_instantiation(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();
		$integration_mock->expects( $this->once() )->method( 'get_vip_config_from_file' )->willReturn( [ 'configs-in-array' ] );
		$integration_mock->expects( $this->once() )->method( 'set_is_active_by_vip' );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );
	}

	public function test__activate_is_setting_up_the_plugins_config_and_marking_the_integration_as_customer_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config_test' ] );

		$this->assertTrue( get_private_property_as_public( FakeIntegration::class, 'is_active_by_customer' )->getValue( $integration ) );
		$this->assertEquals( [ 'config_test' ], $integration->get_config() );
	}

	public function test__is_active_is_returning_false_when_integration_is_not_active_by_any_mean(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertFalse( $integration->is_active() );
	}

	public function test__is_active_is_returning_true_when_integration_is_activated_by_customer(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__is_active_is_returning_true_when_integration_is_activated_by_vip(): void {
		$integration = new FakeIntegration( 'fake' );

		get_private_property_as_public( Integration::class, 'is_active_by_vip' )->setValue( $integration, true );

		$this->assertTrue( $integration->is_active() );
	}

	public function test__is_active_is_returning_true_from_customer_if_integration_is_enabled_by_both_vip_and_customer(): void {
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->disableOriginalConstructor()->getMock();

		get_private_property_as_public( Integration::class, 'is_active_by_customer' )->setValue( $integration_mock, true );
		$integration_mock->expects( $this->exactly( 0 ) )->method( 'get_is_active_by_vip' );

		get_private_method_as_public( FakeIntegration::class, '__construct' )->invoke( $integration_mock, 'fake' );
	}

	public function test__is_active_by_vip_is_resetting_to_false_if_no_condition_is_evaluated(): void {
		$this->test_is_active_by_vip_based_on_given_configs( [], false, false );
	}

	public function test__is_active_by_vip_is_setting_as_false_if_client_status_is_blocked(): void {
		$this->test_is_active_by_vip_based_on_given_configs( [ 'client' => [ 'status' => Client_Integration_Status::BLOCKED ] ], false, false );
	}

	public function test__is_active_by_vip_is_setting_as_false_if_site_status_is_blocked(): void {
		$this->test_is_active_by_vip_based_on_given_configs( [ 'site' => [ 'status' => Client_Integration_Status::BLOCKED ] ], false, false );
	}

	public function test__is_active_by_vip_is_false_if_integration_is_blocked_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip_based_on_given_configs( [ 'network_sites' => [ 1 => [ 'status' => Site_Integration_Status::BLOCKED ] ] ], false, false );
	}

	public function test__is_active_by_vip_is_false_if_integration_is_disabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip_based_on_given_configs( [ 'network_sites' => [ 1 => [ 'status' => Site_Integration_Status::BLOCKED ] ] ], false, false );
	}

	public function test__is_active_by_vip_is_true_if_integration_is_enabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$filter_name = 'fake_integration_setup_filter';
		$this->test_is_active_by_vip_based_on_given_configs( [
			'network_sites' => [
				1 => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => [ 'network sites config' ],
				],
			],
		], true, true, $filter_name );

		$setup_config_value = apply_filters( $filter_name, '' );
		$this->assertEquals( [ 'network sites config' ], $setup_config_value );
	}

	public function test__is_active_by_vip_is_true_without_adding_setup_filter_if_integration_is_enabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip_based_on_given_configs( [ 'network_sites' => [ 1 => [ 'status' => Site_Integration_Status::ENABLED ] ] ], true, false, '' );
	}

	public function test__is_active_by_vip_is_false_if_integration_config_is_not_provided_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_by_vip_based_on_given_configs( [], false, false );
	}

	public function test__is_active_by_vip_is_false_if_integration_is_disabled_on_site(): void {
		$this->test_is_active_by_vip_based_on_given_configs( [ 'site' => [ 'status' => Site_Integration_Status::DISABLED ] ], false, false );
	}

	public function test__is_active_by_vip_is_true_if_integration_is_enabled_on_site(): void {
		$filter_name = 'fake_integration_setup_filter';
		$this->test_is_active_by_vip_based_on_given_configs( [
			'site' => [
				'status' => Site_Integration_Status::ENABLED,
				'config' => [ 'sites config' ],
			],
		], true, true, $filter_name );

		$setup_config_value = apply_filters( $filter_name, '' );
		$this->assertEquals( [ 'sites config' ], $setup_config_value );
	}

	public function test__is_active_by_vip_is_true_without_adding_setup_filter_if_integration_is_enabled_on_site(): void {
		$this->test_is_active_by_vip_based_on_given_configs( [ 'site' => [ 'status' => Site_Integration_Status::ENABLED ] ], true, false, '' );
	}

	public function test__is_active_by_vip_is_false_if_integration_config_is_not_provided_on_site(): void {
		$this->test_is_active_by_vip_based_on_given_configs( [], false, false );
	}

	private function test_is_active_by_vip_based_on_given_configs(
		array $vip_config,
		bool $expected_is_active_by_vip,
		bool $expected_has_config_filter,
		string $filter_name = 'fake_integration_setup_filter'
	) {
		$integration = new FakeIntegration( 'fake' );
		get_private_property_as_public( Integration::class, 'setup_config_filter_name' )->setValue( $integration, $filter_name );
		get_private_property_as_public( Integration::class, 'is_active_by_vip' )->setValue( $integration, true );
		get_private_property_as_public( Integration::class, 'vip_config' )->setValue( $integration, $vip_config );

		$integration->set_is_active_by_vip();

		$this->assertEquals( $expected_is_active_by_vip, $integration->get_is_active_by_vip() );
		$this->assertEquals( $expected_has_config_filter, has_filter( $filter_name ) );
	}

	public function test__get_value_from_vip_config_throws_exception_if_invalid_argument_is_passed(): void {
		$this->expectException( InvalidArgumentException::class );
		$mocked_vip_configs = [];

		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'invalid-config-type', 'key', '' );
	}

	public function test__get_value_from_vip_config_returns_empty_string_if_given_config_type_have_no_data(): void {
		$mocked_vip_configs = [];

		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'client', 'status', '' );
	}

	public function test__get_value_from_vip_config_returns_config_from_client_data(): void {
		$mocked_vip_configs = [
			'client' => [
				'status' => Client_Integration_Status::BLOCKED,
				'config' => array( 'client_configs' ),
			],
		];

		// Test return of string value.
		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'client', 'status', Client_Integration_Status::BLOCKED );

		// Test return of array value.
		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'client', 'config', array( 'client_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_config_from_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$mocked_vip_configs = [
			'site'          => [
				'status' => Site_Integration_Status::BLOCKED,
				'config' => array( 'site_configs' ),
			],
			'network_sites' => [
				1 => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => array( 'network_site_1_configs' ),
				],
				2 => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => array( 'network_site_2_configs' ),
				],
			],
		];

		// Test return of string value.
		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'network_sites', 'status', Site_Integration_Status::ENABLED );

		// Test return of array value.
		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'network_sites', 'config', array( 'network_site_1_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_empty_string_non_existent_key_is_passed(): void {
		$mocked_vip_configs = [
			'site' => [
				'status' => Site_Integration_Status::BLOCKED,
				'config' => array( 'site_configs' ),
			],
		];

		$this->test_get_value_from_vip_config_based_on_given_configs( $mocked_vip_configs, 'site', 'invalid_key', '' );
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
	private function test_get_value_from_vip_config_based_on_given_configs(
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
