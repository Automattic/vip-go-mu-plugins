<?php
/**
 * Test: Integration Config
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

use Org_Integration_Status;
use Env_Integration_Status;
use ErrorException;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_class_property_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Vip_Config_Test extends WP_UnitTestCase {
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->original_error_reporting = error_reporting();
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( error_reporting() & $errno ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, $errno ); // NOSONAR
			}

			return false;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();
		error_reporting( $this->original_error_reporting );
		parent::tearDown();
	}

	public function test__get_vip_config_from_file_returns_null_if_config_file_does_not_exist(): void {
		$slug               = 'dummy';
		$integration_config = new IntegrationVipConfig( $slug );

		$reflection_method = get_class_method_as_public( IntegrationVipConfig::class, 'get_vip_config_from_file' );

		$this->assertNull( $reflection_method->invoke( $integration_config, $slug ) );
	}

	public function test__set_config_does_not_set_the_config_if_received_content_from_file_is_not_of_type_array(): void {
		$mock = $this->get_mock( 'invalid-config' );

		$config = get_class_property_as_public( IntegrationVipConfig::class, 'config' )->getValue( $mock );

		$this->assertEquals( [], $config );
	}

	public function test__is_active_via_vip_returns_false_if_empty_config_is_provided(): void {
		$this->do_test_is_active_via_vip( [], false );
	}

	public function test__is_active_via_vip_returns_false_if_organization_status_is_blocked(): void {
		$this->do_test_is_active_via_vip( [ 'org' => [ 'status' => Org_Integration_Status::BLOCKED ] ], false );
	}

	public function test__is_active_via_vip_returns_false_if_environment_status_is_blocked(): void {
		$this->do_test_is_active_via_vip( [ 'env' => [ 'status' => Org_Integration_Status::BLOCKED ] ], false );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_blocked_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip(
			[
				'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
				'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::BLOCKED ] ],
			],
			false,
		);
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_disabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip(
			[
				'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
				'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::DISABLED ] ],
			],
			false,
		);
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_enabled_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip(
			[
				'env'           => [ 'status' => Env_Integration_Status::DISABLED ],
				'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::ENABLED ] ],
			],
			true,
		);
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_not_present_on_current_network_site_but_enabled_on_environment(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip(
			[
				'env' => [ 'status' => Env_Integration_Status::ENABLED ],
			],
			true,
		);
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_not_provided_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip( [
			'network_sites' => [
				'2' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'site config' ],
				],
			],
		], false );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_disabled_on_environment(): void {
		$this->do_test_is_active_via_vip( [ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ], false );
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_enabled_on_environment(): void {
		$this->do_test_is_active_via_vip( [
			'env' => [
				'status' => Env_Integration_Status::ENABLED,
			],
		], true );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_not_provided_on_environment(): void {
		$this->do_test_is_active_via_vip( [
			'org'           => [
				'status' => Env_Integration_Status::ENABLED,
			],
			'network_sites' => [
				'2' => [
					'status' => Env_Integration_Status::ENABLED,
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
	private function do_test_is_active_via_vip(
		$vip_config,
		bool $expected_is_active_via_vip
	) {
		$mock = $this->get_mock( $vip_config );

		$this->assertEquals( $expected_is_active_via_vip, $mock->is_active_via_vip() );
	}

	public function test__get_env_config_returns_value_from_environment_config(): void {
		$mock = $this->get_mock( [
			'env'           => [
				'status' => Env_Integration_Status::ENABLED,
				'config' => array( 'env-config' ),
			],
			'network_sites' => [
				'1' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'network-site-config' ),
				],
			],
		] );

		$this->assertEquals( array( 'env-config' ), $mock->get_env_config() );
	}

	public function test__get_env_config_returns_value_from_network_site_config(): void {
		$mock = $this->get_mock( [
			'env'           => [
				'status' => Env_Integration_Status::ENABLED,
				'config' => array( 'env-config' ),
			],
			'network_sites' => [
				'1' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'network-site-config' ),
				],
			],
		] );

		$expected = is_multisite() ? array( 'network-site-config' ) : array();
		$this->assertEquals( $expected, $mock->get_network_site_config() );
	}

	public function test__get_value_from_vip_config_returns_null_if_given_config_type_have_no_data(): void {
		$mocked_vip_configs = [];

		$this->do_test_get_value_from_config( $mocked_vip_configs, 'org', 'status', null );
	}

	public function test__get_value_from_vip_config_returns_config_from_organization_data(): void {
		$mocked_vip_configs = [
			'org' => [
				'status' => Org_Integration_Status::BLOCKED,
				'config' => array( 'client_configs' ),
			],
		];

		$this->do_test_get_value_from_config( $mocked_vip_configs, 'org', 'status', Org_Integration_Status::BLOCKED );
		$this->do_test_get_value_from_config( $mocked_vip_configs, 'org', 'config', array( 'client_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_config_of_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$mocked_vip_configs = [
			'env'           => [
				'status' => Env_Integration_Status::BLOCKED,
				'config' => array( 'env_configs' ),
			],
			'network_sites' => [
				'1' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'network_site_1_configs' ),
				],
				'2' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'network_site_2_configs' ),
				],
			],
		];

		$this->do_test_get_value_from_config( $mocked_vip_configs, 'network_sites', 'status', Env_Integration_Status::ENABLED );
		$this->do_test_get_value_from_config( $mocked_vip_configs, 'network_sites', 'config', array( 'network_site_1_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_null_if_non_existent_key_is_passed(): void {
		$mocked_vip_configs = [
			'env' => [
				'status' => Env_Integration_Status::BLOCKED,
				'config' => array( 'env_configs' ),
			],
		];

		$this->do_test_get_value_from_config( $mocked_vip_configs, 'env', 'invalid_key', null );
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
	private function do_test_get_value_from_config(
		array $vip_config,
		string $config_type,
		string $key,
		$expected_value_from_vip_config
	): void {
		$mock = $this->get_mock( $vip_config );

		$config_value = get_class_method_as_public( IntegrationVipConfig::class, 'get_value_from_config' )->invoke( $mock, $config_type, $key );

		$this->assertEquals( $expected_value_from_vip_config, $config_value );
	}

	/**
	 * Get mock.
	 *
	 * @param array|null|string $vip_config
	 *
	 * @return MockObject&IntegrationVipConfig
	 */
	private function get_mock( $vip_config ) {
		/**
		 * Config Mock.
		 *
		 * @var MockObject&IntegrationVipConfig
		 */
		$mock = $this->getMockBuilder( IntegrationVipConfig::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_vip_config_from_file' ] )
			->getMock();

		$mock->method( 'get_vip_config_from_file' )->willReturn( $vip_config );
		$mock->__construct( 'slug' );

		return $mock;
	}
}
