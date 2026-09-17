<?php
/**
 * Test: Connector Controls integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use Env_Integration_Status;
use Org_Integration_Status;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Contracts\ProviderInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\ProviderRegistry;
use WP_UnitTestCase;

class Connector_Controls_Integration_Test extends WP_UnitTestCase {
	private const OPTIONS = [
		'connectors_ai_openai_api_key',
		'connectors_ai_anthropic_api_key',
		'connectors_ai_google_api_key',
	];

	/** @var array<string,string|false> */
	private array $previous_environment_credentials = [];

	/** @var ConnectorControlsIntegration[] */
	private array $recording_integrations = [];

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( '\\wp_get_connectors' ) && 'test_configure_does_not_mutate_runtime_without_the_connectors_api' !== $this->getName( false ) ) {
			$this->markTestSkipped( 'Requires the WordPress Connectors API.' );
		}

		foreach ( [ 'OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GOOGLE_API_KEY' ] as $environment_name ) {
			$this->previous_environment_credentials[ $environment_name ] = getenv( $environment_name );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Isolate provider credentials from the test process environment.
			putenv( $environment_name );
		}
	}

	public function tearDown(): void {
		foreach ( self::OPTIONS as $option_name ) {
			remove_all_filters( "pre_option_{$option_name}" );
			remove_all_filters( "pre_update_option_{$option_name}" );
			remove_all_filters( "sanitize_option_{$option_name}" );
			delete_option( $option_name );
		}
		remove_all_filters( 'script_module_data_options-connectors-wp-admin' );
		foreach ( $this->recording_integrations as $integration ) {
			remove_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] );
		}
		foreach ( $this->previous_environment_credentials as $environment_name => $credential ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Restore the test process environment.
			putenv( false === $credential ? $environment_name : "{$environment_name}={$credential}" );
		}
		Constant_Mocker::clear();

		parent::tearDown();
	}

	private function create_recording_integration(): ConnectorControlsIntegration {
		$integration                    = new class( 'connector-controls' ) extends ConnectorControlsIntegration {
			/** @var array<string,string> */
			public array $applied_runtime_credentials = [];

			protected function set_runtime_credential( string $connector_id, string $credential ): void {
				$this->applied_runtime_credentials[ $connector_id ] = $credential;
			}
		};
		$this->recording_integrations[] = $integration;
		return $integration;
	}

	public function test_managed_credentials_are_always_delivered_at_connector_initialization_without_defining_constants(): void {
		$integration = $this->create_recording_integration();
		$integration->activate(
			[
				'config' => [
					'openai_api_key'    => 'openai-secret',
					'anthropic_api_key' => 'anthropic-secret',
					'google_api_key'    => 'google-secret',
				],
			]
		);

		$integration->configure();

		$this->assertSame( 10, has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
		do_action( 'wp_connectors_init', new \WP_Connector_Registry() );
		$this->assertSame(
			[
				'openai'    => 'openai-secret',
				'anthropic' => 'anthropic-secret',
				'google'    => 'google-secret',
			],
			$integration->applied_runtime_credentials
		);
		foreach ( [ 'OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GOOGLE_API_KEY' ] as $constant_name ) {
			$this->assertFalse( Constant_Mocker::defined( $constant_name ) );
			$this->assertFalse( getenv( $constant_name ) );
		}
		$this->assertTrue( $integration->is_loaded() );
	}

	/**
	 * @dataProvider runtime_credential_source_provider
	 */
	public function test_runtime_credential_preserves_external_source_precedence( $environment_credential, $constant_credential, string $expected_credential ): void {
		if ( false !== $environment_credential ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Exercise Core's environment-variable credential source.
			putenv( "OPENAI_API_KEY={$environment_credential}" );
		}
		if ( null !== $constant_credential ) {
			Constant_Mocker::define( 'OPENAI_API_KEY', $constant_credential );
		}
		$integration = $this->create_recording_integration();
		$integration->activate( [ 'config' => [ 'openai_api_key' => 'platform-secret' ] ] );

		$integration->configure();
		$integration->apply_runtime_credentials();

		$this->assertSame( [ 'openai' => $expected_credential ], $integration->applied_runtime_credentials );
		$this->assertSame( $environment_credential, getenv( 'OPENAI_API_KEY' ) );
		$this->assertSame( null !== $constant_credential, Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		if ( null !== $constant_credential ) {
			$this->assertSame( $constant_credential, Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		}
	}

	public function runtime_credential_source_provider(): iterable {
		yield 'managed credential with no external sources' => [ false, null, 'platform-secret' ];
		yield 'environment precedes managed credential' => [ 'environment-secret', null, 'environment-secret' ];
		yield 'environment precedes constant' => [ 'environment-secret', 'constant-secret', 'environment-secret' ];
		yield 'whitespace environment is non-empty to Core' => [ '   ', 'constant-secret', '   ' ];
		yield 'zero environment is non-empty to Core' => [ '0', null, '0' ];
		yield 'constant precedes managed credential' => [ false, 'constant-secret', 'constant-secret' ];
		yield 'whitespace constant is non-empty to Core' => [ false, '   ', '   ' ];
		yield 'zero constant is non-empty to Core' => [ false, '0', '0' ];
		yield 'empty constant uses managed credential' => [ false, '', 'platform-secret' ];
		yield 'boolean constant uses managed credential' => [ false, false, 'platform-secret' ];
		yield 'integer constant uses managed credential' => [ false, 123, 'platform-secret' ];
		yield 'empty environment with undefined constant' => [ '', null, 'platform-secret' ];
		yield 'empty environment with valid constant' => [ '', 'constant-secret', 'constant-secret' ];
		yield 'empty environment with empty constant' => [ '', '', 'platform-secret' ];
		yield 'empty environment with invalid constant' => [ '', false, 'platform-secret' ];
	}

	/**
	 * @dataProvider unconfigured_credential_provider
	 */
	public function test_no_managed_credential_does_not_apply_runtime_authentication( $managed_credential ): void {
		Constant_Mocker::define( 'OPENAI_API_KEY', 'constant-secret' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Ensure an external source alone does not activate runtime delivery.
		putenv( 'OPENAI_API_KEY=environment-secret' );
		$integration = $this->create_recording_integration();
		$integration->activate( [ 'config' => [ 'openai_api_key' => $managed_credential ] ] );

		$integration->configure();
		$integration->apply_runtime_credentials();

		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertSame( 'constant-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		$this->assertSame( 'environment-secret', getenv( 'OPENAI_API_KEY' ) );
	}

	public function unconfigured_credential_provider(): iterable {
		yield 'missing credential' => [ null ];
		yield 'empty credential' => [ '' ];
		yield 'whitespace credential' => [ '   ' ];
		yield 'invalid credential' => [ false ];
	}

	public function test_direct_credentials_reach_the_ai_client_and_survive_cores_database_handoff(): void {
		$provider                            = new class() implements ProviderInterface {
			public static ProviderAvailabilityInterface $availability;
			public static ModelMetadataDirectoryInterface $model_metadata_directory;

			public static function metadata(): ProviderMetadata {
				return ProviderMetadata::fromArray(
					[
						'id'                   => 'openai',
						'name'                 => 'Test provider',
						'type'                 => 'cloud',
						'authenticationMethod' => 'api_key',
					]
				);
			}

			public static function model( string $model_id, ?ModelConfig $model_config = null ): ModelInterface {
				throw new \LogicException( 'This test does not create models.' );
			}

			public static function availability(): ProviderAvailabilityInterface {
				return self::$availability;
			}

			public static function modelMetadataDirectory(): ModelMetadataDirectoryInterface {
				return self::$model_metadata_directory;
			}
		};
		$provider::$availability             = $this->createMock( ProviderAvailabilityInterface::class );
		$provider::$model_metadata_directory = $this->createMock( ModelMetadataDirectoryInterface::class );

		// Keep this test's provider and authentication out of the shared AI Client registry.
		$registry_property = new \ReflectionProperty( AiClient::class, 'defaultRegistry' );
		$registry_property->setAccessible( true );
		$connector_registry_property = new \ReflectionProperty( \WP_Connector_Registry::class, 'instance' );
		$connector_registry_property->setAccessible( true );
		$previous_ai_registry        = $registry_property->getValue();
		$previous_connector_registry = $connector_registry_property->getValue();
		$ai_registry                 = new ProviderRegistry();
		$connector_registry          = new \WP_Connector_Registry();
		$integration                 = new ConnectorControlsIntegration( 'connector-controls' );
		$registry_property->setValue( null, $ai_registry );
		$connector_registry_property->setValue( null, $connector_registry );

		try {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Reproduce an empty variable masking the managed credential.
			putenv( 'OPENAI_API_KEY=' );
			$ai_registry->registerProvider( get_class( $provider ) );
			\_wp_connectors_register_default_ai_providers( $connector_registry );
			update_option( 'connectors_ai_openai_api_key', 'database-secret' );
			\_wp_connectors_pass_default_keys_to_ai_client();
			$this->assertSame( 'database-secret', $ai_registry->getProviderRequestAuthentication( 'openai' )->getApiKey() );

			$integration->activate(
				[
					'config' => [
						'openai_api_key'    => 'platform-secret',
						'anthropic_api_key' => 'unregistered-provider-secret',
					],
				]
			);
			$integration->configure();
			do_action( 'wp_connectors_init', $connector_registry );
			$authentication = $ai_registry->getProviderRequestAuthentication( 'openai' );
			$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $authentication );
			$this->assertSame( 'platform-secret', $authentication->getApiKey() );

			\_wp_connectors_pass_default_keys_to_ai_client();
			$this->assertSame( $authentication, $ai_registry->getProviderRequestAuthentication( 'openai' ) );
			$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
			$this->assertFalse( Constant_Mocker::defined( 'ANTHROPIC_API_KEY' ) );
			$this->assertSame( '', getenv( 'OPENAI_API_KEY' ) );
		} finally {
			remove_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] );
			$registry_property->setValue( null, $previous_ai_registry );
			$connector_registry_property->setValue( null, $previous_connector_registry );
		}
	}

	public function test_environment_inherits_organization_credentials_when_its_value_is_empty(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => '' ],
				],
			]
		);
		$integration = $this->create_recording_integration();
		$integration->set_vip_config( $config );

		$integration->configure();
		$integration->apply_runtime_credentials();

		$this->assertSame( [ 'openai' => 'org-secret' ], $integration->applied_runtime_credentials );
	}

	public function test_environment_credentials_override_organization_credentials(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'environment-secret' ],
				],
			]
		);
		$integration = $this->create_recording_integration();
		$integration->set_vip_config( $config );

		$integration->configure();

		$integration->apply_runtime_credentials();
		$this->assertSame( [ 'openai' => 'environment-secret' ], $integration->applied_runtime_credentials );
	}

	public function test_environment_can_explicitly_disable_an_organization_credential(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'openai_blocked' => 'true' ],
				],
			]
		);
		$integration = $this->create_recording_integration();
		$integration->set_vip_config( $config );

		$integration->configure();

		$integration->apply_runtime_credentials();
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] ) );
	}

	public function test_disabled_organization_does_not_configure_credentials(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::DISABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
			]
		);
		$integration = $this->create_recording_integration();
		$integration->set_vip_config( $config );
		$integration->activate();

		$integration->configure();

		$integration->apply_runtime_credentials();
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] ) );
		$this->assertFalse( $integration->is_active() );
	}

	public function test_network_site_credentials_override_environment_credentials(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$blog_id = $this->factory()->blog->create_object( [ 'domain' => 'connector-controls.test/2' ] );
		switch_to_blog( $blog_id );

		try {
			$config      = new IntegrationVipConfig(
				'connector-controls',
				[
					'org'           => [
						'status' => Org_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'org-secret' ],
					],
					'env'           => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'environment-secret' ],
					],
					'network_sites' => [
						$blog_id => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'openai_api_key' => 'network-secret' ],
						],
					],
				]
			);
			$integration = $this->create_recording_integration();
			$integration->set_vip_config( $config );

			$integration->configure();

			$integration->apply_runtime_credentials();
			$this->assertSame( [ 'openai' => 'network-secret' ], $integration->applied_runtime_credentials );
		} finally {
			restore_current_blog();
		}
	}

	public function test_network_site_can_explicitly_disable_an_environment_credential(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$blog_id = $this->factory()->blog->create_object( [ 'domain' => 'connector-controls.test/3' ] );
		switch_to_blog( $blog_id );

		try {
			$config      = new IntegrationVipConfig(
				'connector-controls',
				[
					'org'           => [
						'status' => Org_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'org-secret' ],
					],
					'env'           => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'environment-secret' ],
					],
					'network_sites' => [
						$blog_id => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'openai_blocked' => 'true' ],
						],
					],
				]
			);
			$integration = $this->create_recording_integration();
			$integration->set_vip_config( $config );

			$integration->configure();

			$integration->apply_runtime_credentials();
			$this->assertSame( [], $integration->applied_runtime_credentials );
			$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] ) );
		} finally {
			restore_current_blog();
		}
	}

	public function test_managed_database_options_are_inert_and_cannot_be_updated(): void {
		update_option( 'connectors_ai_openai_api_key', 'database-secret' );
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		add_filter(
			'pre_option_connectors_ai_openai_api_key',
			static function () {
				return 'leaked-secret';
			},
			100
		);
		add_filter(
			'pre_update_option_connectors_ai_openai_api_key',
			static function ( $new_value ) {
				return $new_value;
			},
			100
		);

		$this->assertSame( '', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertFalse( update_option( 'connectors_ai_openai_api_key', 'replacement-secret' ) );

		remove_all_filters( 'pre_option_connectors_ai_openai_api_key' );
		$this->assertSame( 'database-secret', get_option( 'connectors_ai_openai_api_key' ) );
	}

	public function test_connector_fields_are_locked_without_changing_existing_external_sources(): void {
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$data        = [
			'connectors' => [
				'openai'    => [ 'authentication' => [ 'keySource' => 'database' ] ],
				'anthropic' => [ 'authentication' => [ 'keySource' => 'env' ] ],
				'custom'    => [ 'authentication' => [ 'keySource' => 'database' ] ],
			],
		];

		$filtered = $integration->lock_connector_fields( $data );

		$this->assertSame( 'constant', $filtered['connectors']['openai']['authentication']['keySource'] );
		$this->assertSame( 'env', $filtered['connectors']['anthropic']['authentication']['keySource'] );
		$this->assertSame( 'database', $filtered['connectors']['custom']['authentication']['keySource'] );
	}

	/**
	 * @dataProvider missing_database_option_provider
	 */
	public function test_managed_database_options_cannot_store_new_credentials( string $option_name, bool $cache_missing_option ): void {
		global $wpdb;

		if ( $cache_missing_option ) {
			$this->assertFalse( get_option( $option_name ) );
		} else {
			wp_cache_delete( 'notoptions', 'options' );
		}
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		add_filter(
			"sanitize_option_{$option_name}",
			static function () {
				return 'replacement-secret';
			},
			100
		);

		$added = add_option( $option_name, 'database-secret' );

		$this->assertSame( '', get_option( $option_name ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify the persisted value without option filters or caches.
		$stored_option = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $option_name ) );
		$this->assertSame( $cache_missing_option ? '' : null, $stored_option->option_value ?? null );
		remove_all_filters( "pre_option_{$option_name}" );
		$this->assertSame( $cache_missing_option, $added );
		$this->assertSame( $cache_missing_option ? '' : false, get_option( $option_name ) );
	}

	public function missing_database_option_provider(): iterable {
		foreach ( self::OPTIONS as $option_name ) {
			yield "{$option_name}: uncached" => [ $option_name, false ];
			yield "{$option_name}: cached missing" => [ $option_name, true ];
		}
	}

	public function test_connector_fields_remain_locked_after_later_filters(): void {
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		add_filter(
			'script_module_data_options-connectors-wp-admin',
			static function ( $data ) {
				foreach ( [ 'openai', 'anthropic', 'google' ] as $connector_id ) {
					$data['connectors'][ $connector_id ]['authentication']['keySource'] = 'database';
				}
				$data['connectors']['custom'] = [ 'authentication' => [ 'keySource' => 'database' ] ];
				return $data;
			},
			101
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Core's script-module data hook contains the module ID.
		$filtered = apply_filters( 'script_module_data_options-connectors-wp-admin', [] );

		foreach ( [ 'openai', 'anthropic', 'google' ] as $connector_id ) {
			$this->assertSame( 'constant', $filtered['connectors'][ $connector_id ]['authentication']['keySource'] );
		}
		$this->assertSame( 'database', $filtered['connectors']['custom']['authentication']['keySource'] );
	}

	public function test_configure_does_not_mutate_runtime_without_the_connectors_api(): void {
		update_option( 'connectors_ai_openai_api_key', 'database-secret' );
		$integration = new class( 'connector-controls' ) extends ConnectorControlsIntegration {
			protected function is_connectors_api_available(): bool {
				return false;
			}
		};
		$integration->activate( [ 'config' => [ 'openai_api_key' => 'platform-secret' ] ] );

		$integration->configure();

		$this->assertFalse( $integration->is_active() );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credentials' ] ) );
		$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		$this->assertSame( 'database-secret', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'pre_option_connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'pre_update_option_connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'sanitize_option_connectors_ai_openai_api_key' ) );
	}
}
