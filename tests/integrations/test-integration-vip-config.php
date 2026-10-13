<?php
/**
 * Test: Integration Config
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use Org_Integration_Status;
use Env_Integration_Status;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_class_property_as_public;

class VIP_Integration_Vip_Config_Test extends WP_UnitTestCase {
	public function test__get_vip_config_from_file_returns_null_if_config_file_does_not_exist(): void {
		$slug               = 'dummy';
		$integration_config = new IntegrationVipConfig( $slug );

		$reflection_method = get_class_method_as_public( IntegrationVipConfig::class, 'get_vip_config_from_file' );

		$this->assertNull( $reflection_method->invoke( $integration_config, $slug ) );
	}

	public function test__set_config_does_not_set_the_config_if_received_content_from_file_is_not_of_type_array(): void {
		/**
		 * The constructor only accepts an array, so the non-array content has to come from the (mocked) config file.
		 *
		 * @var MockObject&IntegrationVipConfig
		 */
		$mock = $this->getMockBuilder( IntegrationVipConfig::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_vip_config_from_file' ] )
			->getMock();
		$mock->method( 'get_vip_config_from_file' )->willReturn( 'invalid-config' );
		$mock->__construct( 'slug' );

		$config = get_class_property_as_public( IntegrationVipConfig::class, 'config' )->getValue( $mock );

		$this->assertEquals( [], $config );
	}

	/**
	 * @dataProvider data_is_active_via_vip
	 */
	public function test__is_active_via_vip( array $vip_config, bool $expected, bool $requires_multisite = false ): void {
		if ( $requires_multisite ) {
			$this->skipWithoutMultisite();
		}

		$this->assertSame( $expected, ( new IntegrationVipConfig( 'slug', $vip_config ) )->is_active_via_vip() );
	}

	public static function data_is_active_via_vip(): array {
		return [
			'empty config'                              => [ [], false ],
			'organization blocked'                      => [
				[
					'org' => [ 'status' => Org_Integration_Status::BLOCKED ],
					'env' => [ 'status' => Env_Integration_Status::ENABLED ],
				],
				false,
			],
			'organization block overrides enabled site' => [
				[
					'org'           => [ 'status' => Org_Integration_Status::BLOCKED ],
					'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
				false,
				true,
			],
			'environment blocked'                       => [ [ 'env' => [ 'status' => Org_Integration_Status::BLOCKED ] ], false ],
			'blocked on current network site'           => [
				[
					'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::BLOCKED ] ],
				],
				false,
				true,
			],
			'disabled on current network site'          => [
				[
					'env'           => [ 'status' => Env_Integration_Status::ENABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::DISABLED ] ],
				],
				false,
				true,
			],
			'enabled on current network site'           => [
				[
					'env'           => [ 'status' => Env_Integration_Status::DISABLED ],
					'network_sites' => [ '1' => [ 'status' => Env_Integration_Status::ENABLED ] ],
				],
				true,
				true,
			],
			'not provided on current network site'      => [
				[
					'network_sites' => [
						'2' => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'site config' ],
						],
					],
				],
				false,
				true,
			],
			'disabled on environment'                   => [ [ 'env' => [ 'status' => Env_Integration_Status::DISABLED ] ], false ],
			'enabled on environment'                    => [ [ 'env' => [ 'status' => Env_Integration_Status::ENABLED ] ], true ],
			'not provided on environment'               => [
				[
					'org'           => [ 'status' => Env_Integration_Status::ENABLED ],
					'network_sites' => [
						'2' => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'site config' ],
						],
					],
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider data_is_enabled_for_org
	 */
	public function test__is_enabled_for_org( string $org_status, bool $expected ): void {
		$config = new IntegrationVipConfig( 'slug', [ 'org' => [ 'status' => $org_status ] ] );

		$this->assertSame( $expected, $config->is_enabled_for_org() );
	}

	public static function data_is_enabled_for_org(): array {
		return [
			'enabled'  => [ Org_Integration_Status::ENABLED, true ],
			'disabled' => [ Org_Integration_Status::DISABLED, false ],
		];
	}

	public function test__get_env_config_and_get_network_site_config_return_their_own_level(): void {
		$config = new IntegrationVipConfig( 'slug', [
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

		$this->assertEquals( array( 'env-config' ), $config->get_env_config() );
		$this->assertEquals( is_multisite() ? array( 'network-site-config' ) : array(), $config->get_network_site_config() );
	}

	/**
	 * @dataProvider data_get_value_from_config
	 */
	public function test__get_value_from_vip_config( array $vip_config, string $config_type, string $key, $expected, bool $requires_multisite = false ): void {
		if ( $requires_multisite ) {
			$this->skipWithoutMultisite();
		}

		$config_value = get_class_method_as_public( IntegrationVipConfig::class, 'get_value_from_config' )->invoke( new IntegrationVipConfig( 'slug', $vip_config ), $config_type, $key );

		$this->assertEquals( $expected, $config_value );
	}

	public static function data_get_value_from_config(): array {
		$org_config     = [
			'org' => [
				'status' => Org_Integration_Status::BLOCKED,
				'config' => array( 'client_configs' ),
			],
		];
		$network_config = [
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
		$env_config     = [
			'env' => [
				'status' => Env_Integration_Status::BLOCKED,
				'config' => array( 'env_configs' ),
			],
		];

		return [
			'config type without data'    => [ [], 'org', 'status', null ],
			'organization status'         => [ $org_config, 'org', 'status', Org_Integration_Status::BLOCKED ],
			'organization config'         => [ $org_config, 'org', 'config', array( 'client_configs' ) ],
			'current network site status' => [ $network_config, 'network_sites', 'status', Env_Integration_Status::ENABLED, true ],
			'current network site config' => [ $network_config, 'network_sites', 'config', array( 'network_site_1_configs' ), true ],
			'non-existent key'            => [ $env_config, 'env', 'invalid_key', null ],
		];
	}

	/**
	 * @dataProvider data_child_configs
	 */
	public function test__child_config_accessors( array $vip_config, string $method, array $args, $expected ): void {
		$this->assertSame( $expected, ( new IntegrationVipConfig( 'slug', $vip_config ) )->$method( ...$args ) );
	}

	public static function data_child_configs(): array {
		$children  = [
			'airtable'      => [
				'type' => 'airtable',
				'env'  => [
					'status' => 'enabled',
					'config' => [ 'sources' => [ [ 'uuid' => 'test-1' ] ] ],
				],
			],
			'google-sheets' => [
				'type' => 'google-sheets',
				'env'  => [
					'status' => 'enabled',
					'config' => [ 'sources' => [ [ 'uuid' => 'test-2' ] ] ],
				],
			],
		];
		$mcp_child = [
			'type' => 'wordpress-mcp',
			'env'  => [
				'status' => 'enabled',
			],
		];

		return [
			'child configs without children'               => [ [], 'get_child_configs', [], [] ],
			'child configs with non-array children'        => [ [ 'children' => 'not-an-array' ], 'get_child_configs', [], [] ],
			'child configs with children'                  => [ [ 'children' => $children ], 'get_child_configs', [], $children ],
			'single child config'                          => [ [ 'children' => [ 'wordpress-mcp' => $mcp_child ] ], 'get_child_config', [ 'wordpress-mcp' ], $mcp_child ],
			'missing single child config'                  => [ [ 'children' => [] ], 'get_child_config', [ 'wordpress-mcp' ], null ],
			'child env configs without children'           => [ [], 'get_child_env_configs', [], [] ],
			'child env configs keep only the config value' => [
				[
					'children' => array_merge(
						$children,
						[
							'invalid-child' => [
								'type' => 'invalid',
								'env'  => [ 'status' => 'enabled' ],
							],
						]
					),
				],
				'get_child_env_configs',
				[],
				[
					'airtable'      => [ 'sources' => [ [ 'uuid' => 'test-1' ] ] ],
					'google-sheets' => [ 'sources' => [ [ 'uuid' => 'test-2' ] ] ],
				],
			],
			'child env configs skip non-array config'      => [
				[
					'children' => [
						'airtable' => [
							'type' => 'airtable',
							'env'  => [
								'status' => 'enabled',
								'config' => 'not-an-array',
							],
						],
					],
				],
				'get_child_env_configs',
				[],
				[],
			],
		];
	}

	public function test__get_org_config_returns_org_config(): void {
		$config = new IntegrationVipConfig(
			'slug',
			[
				'org' => [
					'status' => Org_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'secret' ],
				],
			]
		);

		$this->assertSame( [ 'openai_api_key' => 'secret' ], $config->get_org_config() );
	}
}
