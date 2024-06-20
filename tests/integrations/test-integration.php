<?php
/**
 * Test: Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use Env_Integration_Status;
use ErrorException;
use Org_Integration_Status;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\reset_custom_error_reporting;
use function Automattic\Test\Utils\setup_custom_error_reporting;

require_once __DIR__ . '/fake-integration.php';
require_once __DIR__ . '/fake-multi-config-integration.php';

class VIP_Integration_Test extends WP_UnitTestCase {
	/**
	 * Original error reporting.
	 *
	 * @var int
	 */
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->original_error_reporting = setup_custom_error_reporting();
	}

	public function tearDown(): void {
		reset_custom_error_reporting( $this->original_error_reporting );
		parent::tearDown();
	}

	public function test__slug_is_setting_up_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
	}

	public function test__switch_blog_action_is_added_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 10, has_action( 'switch_blog', [ $integration, 'switch_blog_callback' ] ) );
	}

	public function test__activate_is_marking_the_integration_as_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__activate_is_setting_up_the_plugins_config(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config' => [ 'config_test' ] ] );

		$this->assertEquals( [ 'config_test' ], $integration->get_config() );
	}

	public function test__activate_does_not_activate_the_integration_again_when_the_integration_is_already_loaded(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Prevented activating of integration with slug "fake" because it is already loaded.' );
		/**
		 * Integration mock.
		 *
		 * @var MockObject|FakeIntegration
		 */
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( true );

		$integration_mock->activate();

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test__activate_throws_error_when_same_integration_is_activated_twice(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'VIP Integration with slug "fake" is already activated.' );

		$integration = new FakeIntegration( 'fake' );

		$integration->activate();
		$integration->activate();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__switch_blog_callback_is_setting_the_correct_config_for_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite' );
		}

		$blog_2_id   = $this->factory()->blog->create_object( [ 'domain' => 'integration-test.site/2' ] );
		$integration = new FakeIntegration( 'fake' );
		$integration->set_vip_configs( [
			[
				'network_sites' => [
					get_current_blog_id() => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => array( 'network_site_1_config' ),
					],
					$blog_2_id            => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => array( 'network_site_2_config' ),
					],
				],
			],
		] );

		$integration->activate( [ 'config' => [ 'activate_config' ] ] );

		// By default return config passed via activate().
		$this->assertEquals( array( 'activate_config' ), $integration->get_config() );

		// If blog is switched then return config of current network site.
		switch_to_blog( $blog_2_id );
		$this->assertEquals( array( 'network_site_2_config' ), $integration->get_config() );

		// If blog is restored then return config of the main site.
		restore_current_blog();
		$this->assertEquals( array( 'network_site_1_config' ), $integration->get_config() );
	}

	public function test__is_active_returns_false_when_integration_is_not_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertFalse( $integration->is_active() );
	}

	public function test__is_active_via_vip_returns_false_if_empty_config_is_provided(): void {
		$this->do_test_is_active_via_vip( [], false );
	}

	public function test__is_active_via_vip_returns_false_if_organization_status_is_blocked(): void {
		$this->do_test_is_active_via_vip( [ [ 'org' => [ 'status' => Org_Integration_Status::BLOCKED ] ] ], false );
	}

	public function test__is_active_via_vip_returns_false_if_environment_status_is_blocked(): void {
		$this->do_test_is_active_via_vip( [ [ 'env' => [ 'status' => Org_Integration_Status::BLOCKED ] ] ], false );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_blocked_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip(
			[
				[
					'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::BLOCKED ] ],
				],
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
				[
					'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::DISABLED ] ],
				],
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
				[
					'env'           => [ 'status' => Env_Integration_Status::DISABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
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
				[
					'env' => [ 'status' => Env_Integration_Status::ENABLED ],
				],
			],
			true,
		);
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_not_provided_on_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_is_active_via_vip( [
			[
				'network_sites' => [
					'2' => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'site config' ],
					],
				],
			],
		], false );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_disabled_on_environment(): void {
		$this->do_test_is_active_via_vip( [ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ], false );
	}

	public function test__is_active_via_vip_returns_true_if_integration_is_enabled_on_environment(): void {
		$this->do_test_is_active_via_vip( [
			[
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
				],
			],
		], true );
	}

	public function test__is_active_via_vip_returns_false_if_multi_config_integration_is_blocked_on_organization(): void {
		$vip_configs = [
			[ 'env' => [ 'status' => Env_Integration_Status::BLOCKED ] ],
			[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
			[ 'org' => [ 'status' => Org_Integration_Status::BLOCKED ] ],
		];

		$integration = $this->get_multi_setup_integration( $vip_configs );

		$this->assertEquals( false, $integration->is_active_via_vip() );
	}

	public function test__is_active_via_vip_returns_true_if_multiple_instances_of_integration_are_configured_on_environment(): void {
		$vip_configs = [
			[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
			[ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ],
		];

		$integration = $this->get_multi_setup_integration( $vip_configs );

		$this->assertEquals( true, $integration->is_active_via_vip() );
	}

	public function test__is_active_via_vip_returns_false_if_integration_is_not_provided_on_environment(): void {
		$this->do_test_is_active_via_vip( [
			[
				'org'           => [
					'status' => Env_Integration_Status::ENABLED,
				],
				'network_sites' => [
					'2' => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'site config' ],
					],
				],
			],
		], false );
	}

	/**
	 * Helper function for testing `is_active_via_vip`.
	 *
	 * @param array   $vip_configs
	 * @param boolean $expected_is_active_via_vip
	 *
	 * @return void
	 */
	private function do_test_is_active_via_vip(
		$vip_configs,
		bool $expected_is_active_via_vip
	) {
		$integration = $this->get_integration_with_configs( $vip_configs );

		$this->assertEquals( $expected_is_active_via_vip, $integration->is_active_via_vip() );
	}


	public function test__get_site_configs_returns_value_from_environment_config(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Only valid for non multisite.' );
		}

		$this->do_test_get_site_config(
			[
				[
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
				],
			],
			array( 'env-config' ),
		);
	}

	public function test__get_site_configs_returns_value_from_network_site_config(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_get_site_config(
			[
				[
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
				],
			],
			array( 'network-site-config' ),
		);
	}

	public function test__get_site_configs_returns_value_from_env_config_when_cascading_config_is_true(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$this->do_test_get_site_config(
			[
				[
					'env'           => [
						'status'         => Env_Integration_Status::ENABLED,
						'config'         => array( 'env-config' ),
						'cascade_config' => true,
					],
					'network_sites' => [
						'1' => [
							'status' => Env_Integration_Status::ENABLED,
						],
					],
				],
			],
			array( 'env-config' ),
		);
	}

	public function test__get_site_configs_return_env_configs_of_multi_config_integration(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Only valid for single site.' );
		}

		$vip_configs = [
			[
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'env-1-config' ),
				],
			],
			[
				'env' => [
					'status' => Env_Integration_Status::DISABLED,
					'config' => array( 'env-2-config' ),
				],
			],
		];

		$integration = $this->get_multi_setup_integration( $vip_configs );

		$this->assertEqualsCanonicalizing( [
			[ 'env-1-config' ],
			[ 'env-2-config' ],
		], $integration->get_site_configs() );
	}

	public function test__get_site_configs_return_network_site_configs_of_multi_config_integration(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$vip_configs = [
			[
				'network_sites' => [
					'1' => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => array( '1-config' ),
					],
				],
			],
			[
				'network_sites' => [
					'1' => [
						'status' => Env_Integration_Status::DISABLED,
						'config' => array( '2-config' ),
					],
				],
			],
			[
				'network_sites' => [
					'2' => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => array( '3-config' ),
					],
				],
			],
		];

		$integration = $this->get_multi_setup_integration( $vip_configs );

		$this->assertEqualsCanonicalizing( [
			[ '1-config' ],
			[ '2-config' ],
		], $integration->get_site_configs() );
	}

	/**
	 * Helper function for testing `get_site_configs`.
	 *
	 * @param array $vip_configs
	 * @param mixed $expected_get_site_config
	 *
	 * @return void
	 */
	private function do_test_get_site_config(
		$vip_configs,
		$expected_get_site_config
	) {
		$mock = $this->get_integration_with_configs( $vip_configs );

		$this->assertEquals( $expected_get_site_config, $mock->get_site_configs() );
	}

	public function test__get_value_from_vip_config_trigger_error_if_invalid_argument_is_passed(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'config_type param (invalid) must be one of org, env or network_sites.' );
		$mocked_vip_config = [];

		$this->do_test_get_value_from_config( $mocked_vip_config, 'invalid', 'key', '' );
	}

	public function test__get_value_from_vip_config_returns_null_if_given_config_type_have_no_data(): void {
		$mocked_vip_config = [];

		$this->do_test_get_value_from_config( $mocked_vip_config, 'org', 'status', null );
	}

	public function test__get_value_from_vip_config_returns_config_from_organization_data(): void {
		$mocked_vip_config = [
			'org' => [
				'status' => Org_Integration_Status::BLOCKED,
				'config' => array( 'client_configs' ),
			],
		];

		$this->do_test_get_value_from_config( $mocked_vip_config, 'org', 'status', Org_Integration_Status::BLOCKED );
		$this->do_test_get_value_from_config( $mocked_vip_config, 'org', 'config', array( 'client_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_config_of_current_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$mocked_vip_config = [
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

		$this->do_test_get_value_from_config( $mocked_vip_config, 'network_sites', 'status', Env_Integration_Status::ENABLED );
		$this->do_test_get_value_from_config( $mocked_vip_config, 'network_sites', 'config', array( 'network_site_1_configs' ) );
	}

	public function test__get_value_from_vip_config_returns_null_if_non_existent_key_is_passed(): void {
		$mocked_vip_config = [
			'env' => [
				'status' => Env_Integration_Status::BLOCKED,
				'config' => array( 'env_configs' ),
			],
		];

		$this->do_test_get_value_from_config( $mocked_vip_config, 'env', 'invalid_key', null );
	}

	/**
	 * Helper function for testing `get_value_from_vip_config`.
	 *
	 * @param array      $vip_configs
	 * @param string     $config_type
	 * @param string     $key
	 * @param null|array $expected_value_from_vip_config
	 *
	 * @return void
	 */
	private function do_test_get_value_from_config(
		array $vip_configs,
		string $config_type,
		string $key,
		$expected_value_from_vip_config
	): void {
		$integration  = $this->get_integration_with_configs( $vip_configs );
		$config_value = get_class_method_as_public( Integration::class, 'get_value_from_config' )->invoke( $integration, $vip_configs, $config_type, $key );

		$this->assertEquals( $expected_value_from_vip_config, $config_value );
	}

	/**
	 * Get `Integration` with configs.
	 *
	 * @param array $vip_configs
	 */
	private function get_integration_with_configs( $vip_configs ): Integration {
		$integration = new FakeIntegration( 'fake' );

		$integration->set_vip_configs( $vip_configs );

		return $integration;
	}

	/**
	 * Get `Integration` having multiple setups.
	 *
	 * @param array $vip_configs
	 */
	private function get_multi_setup_integration( $vip_configs ): Integration {
		$integration = new FakeMultiSetupIntegration( 'fake-multi-setup' );

		$integration->set_vip_configs( $vip_configs );

		return $integration;
	}
}
