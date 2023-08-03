<?php
/**
 * Test: Integration Config
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use Client_Integration_Status;
use InvalidArgumentException;
use Site_Integration_Status;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_class_property_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Config_Test extends WP_UnitTestCase {
	public function test__get_vip_config_from_file_returns_null_if_config_file_does_not_exist(): void {
		$slug               = 'dummy';
		$integration_config = new IntegrationConfig( $slug );

		$reflection_method = get_class_method_as_public( IntegrationConfig::class, 'get_vip_config_from_file' );

		$this->assertNull( $reflection_method->invoke( $integration_config, $slug ) );
	}

	public function test__set_config_does_not_set_the_config_if_received_content_from_file_is_not_of_type_array(): void {
		$mock = $this->get_mock( 'invalid-config' );

		$config = get_class_property_as_public( IntegrationConfig::class, 'config' )->getValue( $mock );

		$this->assertEquals( [], $config );
	}

	public function test__is_active_via_vip_returns_false_if_empty_config_is_provided(): void {
		$this->test_is_active_via_vip( [], false );
	}

	public function test__is_active_via_vip_returns_false_if_client_status_is_blocked(): void {
		$this->test_is_active_via_vip( [ 'client' => [ 'status' => Client_Integration_Status::BLOCKED ] ], false );
	}

	public function test__is_active_via_vip_returns_false_if_site_status_is_blocked(): void {
		$this->test_is_active_via_vip( [ 'site' => [ 'status' => Client_Integration_Status::BLOCKED ] ], false );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_blocked_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_via_vip(
			[
				'site'          => [ 'status' => Site_Integration_Status::ENABLED ],
				'network_sites' => [ '1' => [ 'status' => Site_Integration_Status::BLOCKED ] ],
			],
			false,
		);
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_disabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_via_vip(
			[
				'site'          => [ 'status' => Site_Integration_Status::ENABLED ],
				'network_sites' => [ '1' => [ 'status' => Site_Integration_Status::DISABLED ] ],
			],
			false,
		);
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_enabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_via_vip(
			[
				'site'          => [ 'status' => Site_Integration_Status::DISABLED ],
				'network_sites' => [ '1' => [ 'status' => Site_Integration_Status::ENABLED ] ],
			],
			true,
		);
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_not_present_on_current_network_site_but_enabled_on_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_via_vip(
			[
				'site' => [ 'status' => Site_Integration_Status::ENABLED ],
			],
			true,
		);
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_not_provided_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_is_active_via_vip( [
			'network_sites' => [
				'2' => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => [ 'site config' ],
				],
			],
		], false );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_disabled_on_site(): void {
		$this->test_is_active_via_vip( [ 'site' => [ 'status' => Site_Integration_Status::DISABLED ] ], false );
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_enabled_on_site(): void {
		$this->test_is_active_via_vip( [
			'site' => [
				'status' => Site_Integration_Status::ENABLED,
			],
		], true );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_not_provided_on_site(): void {
		$this->test_is_active_via_vip( [
			'client'        => [
				'status' => Site_Integration_Status::ENABLED,
			],
			'network_sites' => [
				'2' => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => [ 'site config' ],
				],
			],
		], false );
	}

	/**
	 * Helper function for testing `is_active_via_vip`.
	 *
	 * @param array|null $vip_config
	 * @param boolean    $expected_is_active_via_vip
	 *
	 * @return void
	 */
	private function test_is_active_via_vip(
		$vip_config,
		bool $expected_is_active_via_vip
	) {
		$mock = $this->get_mock( $vip_config );

		$this->assertEquals( $expected_is_active_via_vip, $mock->is_active_via_vip() );
	}

	public function test__get_site_config_returns_value_from_site_config(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Only valid for non multisite.' );
		}

		$this->test_get_site_config(
			[
				'site'          => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => array( 'site-config' ),
				],
				'network_sites' => [
					'1' => [
						'status' => Site_Integration_Status::ENABLED,
						'config' => array( 'network-site-config' ),
					],
				],
			],
			array( 'site-config' ),
		);
	}

	public function test__get_site_config_returns_value_from_network_site_config(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->test_get_site_config(
			[
				'site'          => [
					'status' => Site_Integration_Status::ENABLED,
					'config' => array( 'site-config' ),
				],
				'network_sites' => [
					'1' => [
						'status' => Site_Integration_Status::ENABLED,
						'config' => array( 'network-site-config' ),
					],
				],
			],
			array( 'network-site-config' ),
		);
	}

	/**
	 * Helper function for testing `get_site_config`.
	 *
	 * @param array $vip_config
	 * @param mixed $expected_get_site_config
	 *
	 * @return void
	 */
	private function test_get_site_config(
		$vip_config,
		$expected_get_site_config
	) {
		$mock = $this->get_mock( $vip_config );

		$this->assertEquals( $expected_get_site_config, $mock->get_site_config() );
	}

	public function test__get_value_from_vip_config_throws_exception_if_invalid_argument_is_passed(): void {
		$this->expectException( InvalidArgumentException::class );
		$mocked_vip_configs = [];

		$this->test_get_value_from_config( $mocked_vip_configs, 'invalid-config-type', 'key', '' );
	}

	public function test__get_value_from_vip_config_returns_null_if_given_config_type_have_no_data(): void {
		$mocked_vip_configs = [];

		$this->test_get_value_from_config( $mocked_vip_configs, 'client', 'status', null );
	}

	public function test__get_value_from_vip_config_returns_config_from_client_data(): void {
		$mocked_vip_configs = [
			'client' => [
				'status' => Client_Integration_Status::BLOCKED,
				'config' => array( 'client_configs' ),
			],
		];

		$this->test_get_value_from_config( $mocked_vip_configs, 'client', 'status', Client_Integration_Status::BLOCKED );
		$this->test_get_value_from_config( $mocked_vip_configs, 'client', 'config', array( 'client_configs' ) );
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

		$this->test_get_value_from_config( $mocked_vip_configs, 'network_sites', 'status', Site_Integration_Status::ENABLED );
		$this->test_get_value_from_config( $mocked_vip_configs, 'network_sites', 'config', array( 'network_site_1_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_null_if_non_existent_key_is_passed(): void {
		$mocked_vip_configs = [
			'site' => [
				'status' => Site_Integration_Status::BLOCKED,
				'config' => array( 'site_configs' ),
			],
		];

		$this->test_get_value_from_config( $mocked_vip_configs, 'site', 'invalid_key', null );
	}

	/**
	 * Helper function for testing get_value_from_vip_config.
	 *
	 * @param array      $vip_config
	 * @param string     $config_type
	 * @param string     $key
	 * @param null|array $expected_value_from_vip_config
	 *
	 * @return void
	 */
	private function test_get_value_from_config(
		array $vip_config,
		string $config_type,
		string $key,
		$expected_value_from_vip_config
	): void {
		$mock = $this->get_mock( $vip_config );

		$config_value = get_class_method_as_public( IntegrationConfig::class, 'get_value_from_config' )->invoke( $mock, $config_type, $key );

		$this->assertEquals( $expected_value_from_vip_config, $config_value );
	}

	/**
	 * Get mock.
	 *
	 * @param array|null|string $vip_config
	 *
	 * @return MockObject
	 */
	private function get_mock( $vip_config ) {
		/**
		 * Config Mock.
		 *
		 * @var MockObject
		 */
		$mock = $this->getMockBuilder( IntegrationConfig::class )
								->disableOriginalConstructor()
								->setMethods( [
									'get_vip_config_from_file',
								] )
								->getMock();

		$mock->method( 'get_vip_config_from_file' )->willReturn( $vip_config );
		$mock->__construct( 'slug' );

		return $mock;
	}
}
