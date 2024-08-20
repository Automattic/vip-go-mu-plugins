<?php
/**
 * Test: Remote Data Blocks Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use Env_Integration_Status;

use function Automattic\Test\Utils\get_class_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class VIP_Remote_Data_Blocks_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'remote-data-blocks';

	public function tearDown(): void {
		parent::tearDown();

		Constant_Mocker::clear();
	}

	public function test_constructor_is_instantiating_DataSourceIntegration_objects_based_on_slugs(): void {
		$this->set_integrations( new Integrations() );

		$integration = new RemoteDataBlocksIntegration( $this->slug );

		$data_source_integrations = get_class_property_as_public( RemoteDataBlocksIntegration::class, 'data_source_integrations' )->getValue( $integration );
		$this->assertNotEquals( 0, count( $data_source_integrations ) );
		foreach ( $data_source_integrations as $data_source_integration ) {
			$this->assertInstanceOf( DataSourceIntegration::class, $data_source_integration );
		}
	}

	public function test_is_active_via_vip_return_false_if_remote_data_blocks_integration_is_not_enabled(): void {
		$this->do_test_is_active_via_vip(
			[
				'airtable' => [
					[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
			],
			false
		);
	}

	public function test_is_active_via_vip_return_false_if_no_data_source_integration_is_active(): void {
		$this->do_test_is_active_via_vip(
			[
				'remote-data-blocks' => [
					[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
				'airtable'           => [
					[ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ],
				],
				'shopify'            => [
					[ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ],
					[ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ],
				],
			],
			false
		);
	}

	public function test_is_active_via_vip_return_true_if_any_data_source_integration_is_active(): void {
		$this->do_test_is_active_via_vip(
			[
				'remote-data-blocks' => [
					[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
				'airtable'           => [
					[ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ],
				],
				'shopify'            => [
					[ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ],
					[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
			],
			true
		);
	}

	/**
	 * Helper function for testing `is_active_via_vip`.
	 *
	 * @param array<VipIntegrationConfig> $vip_configs
	 * @param boolean                     $expected_is_active_via_vip
	 *
	 * @return void
	 */
	private function do_test_is_active_via_vip(
		$vip_configs,
		bool $expected_is_active_via_vip
	) {
		$integration = $this->get_remote_data_blocks_integration( $vip_configs );

		$this->assertEquals( $expected_is_active_via_vip, $integration->is_active_via_vip() );
	}

	public function test_get_site_configs_return_empty_array_if_there_are_no_configs_of_data_source_integrations(): void {
		$this->do_test_get_site_configs( [], [] );

		$this->do_test_get_site_configs(
			[
				'shopify' => [
					[ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
			],
			[]
		);
	}

	public function test_get_site_configs_return_combined_configs_of_all_data_source_integrations(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Only valid for non multisite.' );
		}

		$this->do_test_get_site_configs(
			[
				'airtable' => [
					[
						'env' => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [
								'config_key' => 'airtable_source_key',
							],
						],
					],
				],
				'shopify'  => [
					[
						'env' => [
							'status' => Env_Integration_Status::DISABLED,
						],
					],
					[
						'env' => [
							'status' => Env_Integration_Status::DISABLED,
							'config' => [
								'config_key' => 'shopify_source_2_key',
							],
						],
					],
					[
						'label' => 'Shopify Source 3',
						'env'   => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [
								'config_key' => 'shopify_source_3_key',
							],
						],
					],
					[
						'env' => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [
								'config_key' => 'shopify_source_4_key',
								'store_id'   => 'shopify_source_4_source_id',
							],
						],
					],
				],
			],
			[
				[
					'type'       => 'airtable',
					'config_key' => 'airtable_source_key',
				],
				[
					'type'       => 'shopify',
					'config_key' => 'shopify_source_2_key',
				],
				[
					'type'       => 'shopify',
					'label'      => 'Shopify Source 3',
					'config_key' => 'shopify_source_3_key',
				],
				[
					'type'       => 'shopify',
					'config_key' => 'shopify_source_4_key',
					'store_id'   => 'shopify_source_4_source_id',
				],
			]
		);
	}

	/**
	 * Helper function for testing `get_site_configs`.
	 *
	 * @param array<VipIntegrationConfig> $vip_configs
	 * @param mixed                       $expected_get_site_configs
	 *
	 * @return void
	 */
	private function do_test_get_site_configs(
		$vip_configs,
		$expected_get_site_configs
	) {
		$integration = $this->get_remote_data_blocks_integration( $vip_configs );

		$this->assertEquals( $expected_get_site_configs, $integration->get_site_configs() );
	}

	public function test_is_loaded_return_true_if_constant_is_not_defined(): void {
		$integration = $this->get_remote_data_blocks_integration( [] );

		$this->assertFalse( $integration->is_loaded() );
	}

	public function test_is_loaded_return_true_if_constant_is_defined(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS__PLUGIN_VERSION', '1.0' );

		$integration = $this->get_remote_data_blocks_integration( [] );

		$this->assertTrue( $integration->is_loaded() );
	}

	public function test__config_filter_is_returning_configuration_of_all_data_sources(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Only valid for non multisite.' );
		}

		$this->get_remote_data_blocks_integration(
			[
				'remote-data-blocks' => [
					[
						'env' => [ 'status' => Env_Integration_Status::ENABLED ],
					],
				],
				'airtable'           => [
					[
						'env' => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'config_key' => 'airtable_source_key' ],
						],
					],
				],
				'shopify'            => [
					[
						'env' => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'config_key' => 'shopify_source_key' ],
						],
					],
				],
			]
		);

		$this->assertEquals(
			[
				[
					'type'       => 'airtable',
					'config_key' => 'airtable_source_key',
				],
				[
					'type'       => 'shopify',
					'config_key' => 'shopify_source_key',
				],
			],
			apply_filters( 'vip_integration_remote_data_blocks_config', [] )
		);
	}

	/**
	 * Helper function for get "Remote Data Blocks" integration object.
	 *
	 * @param array<VipIntegrationConfig> $vip_configs
	 */
	private function get_remote_data_blocks_integration( $vip_configs ): RemoteDataBlocksIntegration {
		$integrations = new Integrations();
		$this->set_integrations( $integrations );
		$integrations_config = $this->set_integrations_config( $vip_configs );

		$integration = new RemoteDataBlocksIntegration( $this->slug );
		$integrations->register( $integration );
		$integrations->activate_platform_integrations( $integrations_config );

		return $integration;
	}

	/**
	 * Set integrations mock.
	 *
	 * @param MockObject|Integrations $mock
	 */
	private function set_integrations( $mock ): void {
		$instance = IntegrationsSingleton::instance();
		get_class_property_as_public( IntegrationsSingleton::class, 'instance' )->setValue( $instance, $mock );
	}

	/**
	 * Set config on VIPIntegrationsConfig and return it.
	 *
	 * @param array<mixed> $configs
	 *
	 * @return VipIntegrationsConfig
	 */
	private function set_integrations_config( $configs ) {
		$obj = new VipIntegrationsConfig();

		get_class_property_as_public( VipIntegrationsConfig::class, 'configs' )->setValue( $obj, $configs );

		return $obj;
	}
}
