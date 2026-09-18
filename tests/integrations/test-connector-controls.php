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
			remove_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] );
			remove_action( 'wp_connectors_init', [ $integration, 'describe_managed_connectors' ], PHP_INT_MAX );
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

	public function test_managed_credentials_define_constants_without_runtime_fallbacks(): void {
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

		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$connector_registry = new \WP_Connector_Registry();
		do_action( 'wp_connectors_init', $connector_registry );
		foreach ( [ 'openai', 'anthropic', 'google' ] as $connector_id ) {
			$this->assertFalse( $connector_registry->is_registered( $connector_id ), 'Do not recreate connectors removed by other code.' );
		}
		$this->assertSame( [], $integration->applied_runtime_credentials );
		foreach ( [
			'OPENAI_API_KEY'    => 'openai-secret',
			'ANTHROPIC_API_KEY' => 'anthropic-secret',
			'GOOGLE_API_KEY'    => 'google-secret',
		] as $constant_name => $credential ) {
			$this->assertTrue( Constant_Mocker::defined( $constant_name ) );
			$this->assertSame( $credential, Constant_Mocker::constant( $constant_name ) );
			$this->assertFalse( getenv( $constant_name ) );
		}
		$this->assertTrue( $integration->is_loaded() );
	}

	/**
	 * @dataProvider runtime_credential_source_provider
	 */
	public function test_runtime_credential_preserves_external_source_precedence( $environment_credential, $constant_credential, ?string $expected_fallback ): void {
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
		$integration->apply_runtime_credential_fallbacks();

		$this->assertSame( null === $expected_fallback ? [] : [ 'openai' => $expected_fallback ], $integration->applied_runtime_credentials );
		$this->assertSame( null === $expected_fallback ? false : 10, has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
		$this->assertSame( $environment_credential, getenv( 'OPENAI_API_KEY' ) );
		$should_define_constant = null === $constant_credential && ( false === $environment_credential || '' === $environment_credential );
		$this->assertSame( null !== $constant_credential || $should_define_constant, Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		if ( null !== $constant_credential ) {
			$this->assertSame( $constant_credential, Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		} elseif ( $should_define_constant ) {
			$this->assertSame( 'platform-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		}
	}

	public function runtime_credential_source_provider(): iterable {
		yield 'managed credential with no external sources' => [ false, null, null ];
		yield 'environment precedes managed credential' => [ 'environment-secret', null, null ];
		yield 'environment precedes constant' => [ 'environment-secret', 'constant-secret', null ];
		yield 'whitespace environment is non-empty to Core' => [ '   ', 'constant-secret', null ];
		yield 'zero environment is non-empty to Core' => [ '0', null, null ];
		yield 'constant precedes managed credential' => [ false, 'constant-secret', null ];
		yield 'whitespace constant is non-empty to Core' => [ false, '   ', null ];
		yield 'zero constant is non-empty to Core' => [ false, '0', null ];
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
	public function test_no_managed_credential_leaves_valid_external_sources_unchanged( $managed_credential ): void {
		Constant_Mocker::define( 'OPENAI_API_KEY', 'constant-secret' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Ensure an external source alone does not activate runtime delivery.
		putenv( 'OPENAI_API_KEY=environment-secret' );
		$integration = $this->create_recording_integration();
		$integration->activate( [ 'config' => [ 'openai_api_key' => $managed_credential ] ] );

		$integration->configure();
		$integration->apply_runtime_credential_fallbacks();

		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertSame( 'constant-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		$this->assertSame( 'environment-secret', getenv( 'OPENAI_API_KEY' ) );
		$this->assertFalse( Constant_Mocker::defined( 'ANTHROPIC_API_KEY' ) );
		$this->assertFalse( Constant_Mocker::defined( 'GOOGLE_API_KEY' ) );
	}

	public function unconfigured_credential_provider(): iterable {
		yield 'missing credential' => [ null ];
		yield 'empty credential' => [ '' ];
		yield 'whitespace credential' => [ '   ' ];
		yield 'invalid credential' => [ false ];
	}

	private function with_test_registry( callable $test, bool $register_provider = true ): void {
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
		$registry_property           = new \ReflectionProperty( AiClient::class, 'defaultRegistry' );
		$connector_registry_property = new \ReflectionProperty( \WP_Connector_Registry::class, 'instance' );
		$previous_ai_registry        = $registry_property->getValue();
		$previous_connector_registry = $connector_registry_property->getValue();
		$ai_registry                 = new ProviderRegistry();
		$connector_registry          = new \WP_Connector_Registry();
		$registry_property->setValue( null, $ai_registry );
		$connector_registry_property->setValue( null, $connector_registry );

		try {
			if ( $register_provider ) {
				$ai_registry->registerProvider( get_class( $provider ) );
			}
			$test( $ai_registry, $connector_registry, get_class( $provider ) );
		} finally {
			$registry_property->setValue( null, $previous_ai_registry );
			$connector_registry_property->setValue( null, $previous_connector_registry );
		}
	}

	public function test_empty_environment_fallback_reaches_the_ai_client_and_survives_cores_database_handoff(): void {
		$this->with_test_registry( function ( $ai_registry, $connector_registry ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Reproduce an empty variable masking the managed credential.
			putenv( 'OPENAI_API_KEY=' );
			\_wp_connectors_register_default_ai_providers( $connector_registry );
			update_option( 'connectors_ai_openai_api_key', 'database-secret' );
			\_wp_connectors_pass_default_keys_to_ai_client();
			$this->assertSame( 'database-secret', $ai_registry->getProviderRequestAuthentication( 'openai' )->getApiKey() );

			$integration                    = new ConnectorControlsIntegration( 'connector-controls' );
			$this->recording_integrations[] = $integration;
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
			$this->assertSame( 'platform-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
			$this->assertSame( 'unregistered-provider-secret', Constant_Mocker::constant( 'ANTHROPIC_API_KEY' ) );
			$this->assertSame( '', getenv( 'OPENAI_API_KEY' ) );
		} );
	}

	/**
	 * @dataProvider runtime_status_provider
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_runtime_status_observes_authentication_without_exposing_credentials( $environment, $constant, bool $managed, string $expected_source, bool $blocked = false ): void {
		if ( false !== $environment ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Isolate external credential sources.
			putenv( "OPENAI_API_KEY={$environment}" );
		}
		if ( null !== $constant ) {
			Constant_Mocker::define( 'OPENAI_API_KEY', $constant );
		}

		$this->with_test_registry( function ( $registry, $connector_registry, $provider_class ) use ( $managed, $expected_source, $blocked ) {
			$integration                    = new ConnectorControlsIntegration( 'connector-controls' );
			$this->recording_integrations[] = $integration;
			$config                         = $managed ? [ 'openai_api_key' => 'managed-test-secret' ] : [];
			if ( $blocked ) {
				$config['openai_blocked'] = 'true';
			}
			$integration->activate( [ 'config' => $config ] );
			$integration->configure();
			$this->expose_mocked_constant_to_ai_client();
			$registry->registerProvider( $provider_class );
			\_wp_connectors_register_default_ai_providers( $connector_registry );
			$original_connector = $connector_registry->get_registered( 'openai' );
			$custom_connector   = $connector_registry->register( 'custom', [
				'name'           => 'Custom connector',
				'description'    => 'Customer description.',
				'type'           => 'custom',
				'authentication' => [ 'method' => 'none' ],
			] );
			do_action( 'wp_connectors_init', $connector_registry );
			$descriptions                       = [
				'integration'          => 'Credentials managed in VIP Integration Center.',
				'environment_variable' => 'An environment variable supplies this credential and takes precedence over VIP Integration Center.',
				'php_constant'         => 'A customer-defined PHP constant supplies this credential and takes precedence over VIP Integration Center.',
				'none'                 => 'No credential is configured. Configure a credential in VIP Integration Center.',
			];
			$original_connector['description'] .= ' ' . $descriptions[ $expected_source ];
			$this->assertSame( $original_connector, $connector_registry->get_registered( 'openai' ), 'Only the description changes; preserve authentication, logo, and plugin metadata.' );
			$this->assertSame( $custom_connector, $connector_registry->get_registered( 'custom' ) );
			$this->assertStringEndsWith( 'Manage credentials in VIP Integration Center. The AI provider is unavailable.', $connector_registry->get_registered( 'anthropic' )['description'] );
			$this->assertStringEndsWith( 'Manage credentials in VIP Integration Center. The AI provider is unavailable.', $connector_registry->get_registered( 'google' )['description'] );

			$expected = [
				'active'    => true,
				'providers' => [
					'openai'    => [
						'source' => $expected_source,
						'status' => 'none' === $expected_source ? 'unconfigured' : 'configured',
					],
					'anthropic' => [
						'source' => 'none',
						'status' => 'provider_unavailable',
					],
					'google'    => [
						'source' => 'none',
						'status' => 'provider_unavailable',
					],
				],
			];
			$this->assertSame( $expected, $integration->get_runtime_status() );
			$this->assertSame( $expected, $integration->get_runtime_status(), 'Repeated reports must remain stable for SDS hashing.' );

			// Observe later overrides, not just the credential originally selected.
			$registry->setProviderRequestAuthentication( 'openai', new ApiKeyRequestAuthentication( 'later-custom-secret' ) );
			$this->assertSame( [
				'source' => 'unknown',
				'status' => 'configured',
			], $integration->get_runtime_status()['providers']['openai'] );
			$registry->setProviderRequestAuthentication( 'openai', new ApiKeyRequestAuthentication( '' ) );
			$this->assertSame( [
				'source' => 'none',
				'status' => 'unconfigured',
			], $integration->get_runtime_status()['providers']['openai'] );
		}, false );
	}

	public function runtime_status_provider(): iterable {
		yield 'managed' => [ false, null, true, 'integration' ];
		yield 'environment overrides managed and constant' => [ 'environment-test-secret', 'constant-test-secret', true, 'environment_variable' ];
		yield 'constant overrides managed' => [ false, 'constant-test-secret', true, 'php_constant' ];
		yield 'preexisting constant equal to managed remains external' => [ false, 'managed-test-secret', true, 'php_constant' ];
		yield 'empty environment falls through to constant' => [ '', 'constant-test-secret', true, 'php_constant' ];
		yield 'empty environment falls through to managed constant' => [ '', null, true, 'integration' ];
		yield 'empty externals fall through to managed' => [ '', '', true, 'integration' ];
		yield 'invalid existing constant uses managed fallback' => [ false, false, true, 'integration' ];
		yield 'whitespace environment remains external' => [ '   ', null, true, 'environment_variable' ];
		yield 'zero environment remains external' => [ '0', null, true, 'environment_variable' ];
		yield 'whitespace constant remains external' => [ false, '   ', true, 'php_constant' ];
		yield 'zero constant remains external' => [ false, '0', true, 'php_constant' ];
		yield 'environment without assignment' => [ 'environment-test-secret', null, false, 'environment_variable' ];
		yield 'constant without assignment' => [ false, 'constant-test-secret', false, 'php_constant' ];
		yield 'empty environment with constant without assignment' => [ '', 'constant-test-secret', false, 'php_constant' ];
		yield 'empty environment with constant and blocked managed assignment' => [ '', 'constant-test-secret', false, 'php_constant', true ];
		yield 'no credential' => [ false, null, false, 'none' ];
		yield 'empty environment without assignment' => [ '', null, false, 'none' ];
	}

	/**
	 * The integration's namespace mocks constant functions. Mirror its selected
	 * constant into the isolated PHP process so the unmodified AI Client performs
	 * its real environment/constant discovery, rather than injecting test auth.
	 */
	private function expose_mocked_constant_to_ai_client(): void {
		if ( Constant_Mocker::defined( 'OPENAI_API_KEY' ) ) {
			$this->assertFalse( \defined( 'OPENAI_API_KEY' ) );
			\define( 'OPENAI_API_KEY', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_managed_constant_is_available_to_provider_registered_after_connector_initialization(): void {
		$this->with_test_registry( function ( $registry, $connector_registry, $provider_class ) {
			$integration = new ConnectorControlsIntegration( 'connector-controls' );
			$integration->activate( [ 'config' => [ 'openai_api_key' => 'managed-test-secret' ] ] );
			$integration->configure();
			$integration->configure();
			$this->expose_mocked_constant_to_ai_client();
			$this->assertSame( 'managed-test-secret', \constant( 'OPENAI_API_KEY' ) );
			$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
			do_action( 'wp_connectors_init', $connector_registry );
			$this->assertSame( 'provider_unavailable', $integration->get_runtime_status()['providers']['openai']['status'] );

			$registry->registerProvider( $provider_class );
			$this->assertSame( 'managed-test-secret', $registry->getProviderRequestAuthentication( 'openai' )->getApiKey() );
			$this->assertSame( [
				'source' => 'integration',
				'status' => 'configured',
			], $integration->get_runtime_status()['providers']['openai'] );
		}, false );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_normal_constant_path_preserves_earlier_explicit_authentication(): void {
		$this->with_test_registry( function ( $registry, $connector_registry, $provider_class ) {
			$existing_authentication = new ApiKeyRequestAuthentication( 'earlier-custom-secret' );
			$registry->setProviderRequestAuthentication( 'openai', $existing_authentication );
			$integration = new ConnectorControlsIntegration( 'connector-controls' );
			$integration->activate( [ 'config' => [ 'openai_api_key' => 'managed-test-secret' ] ] );
			$integration->configure();
			$this->expose_mocked_constant_to_ai_client();
			$registry->registerProvider( $provider_class );
			\_wp_connectors_register_default_ai_providers( $connector_registry );
			do_action( 'wp_connectors_init', $connector_registry );

			$this->assertSame( $existing_authentication, $registry->getProviderRequestAuthentication( 'openai' ) );
			$this->assertStringEndsWith( 'Manage credential assignments in VIP Integration Center. The credential source could not be determined.', $connector_registry->get_registered( 'openai' )['description'] );
			$this->assertSame( [
				'source' => 'unknown',
				'status' => 'configured',
			], $integration->get_runtime_status()['providers']['openai'] );
		} );
	}

	public function test_inactive_runtime_report_is_explicit_and_does_not_initialize_the_ai_client(): void {
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$this->assertSame( [
			'active'    => false,
			'providers' => array_fill_keys( [ 'openai', 'anthropic', 'google' ], [
				'source' => 'unknown',
				'status' => 'unknown',
			] ),
		], $integration->get_runtime_status() );
	}

	/** @dataProvider runtime_active_provider */
	public function test_switched_blog_does_not_report_another_blogs_authentication( bool $active ): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		if ( $active ) {
			$integration->configure();
		}
		$blog_id = $this->factory()->blog->create_object( [ 'domain' => 'connector-controls-report.test' ] );
		switch_to_blog( $blog_id );
		try {
			$this->assertNull( $integration->get_runtime_status() );
		} finally {
			restore_current_blog();
		}
	}

	public function runtime_active_provider(): iterable {
		yield 'active' => [ true ];
		yield 'inactive' => [ false ];
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
		$integration->apply_runtime_credential_fallbacks();

		$this->assertSame( 'org-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
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

		$integration->apply_runtime_credential_fallbacks();
		$this->assertSame( 'environment-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
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

		$integration->apply_runtime_credential_fallbacks();
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
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

		$integration->apply_runtime_credential_fallbacks();
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
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

			$integration->apply_runtime_credential_fallbacks();
			$this->assertSame( 'network-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
			$this->assertSame( [], $integration->applied_runtime_credentials );
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

			$integration->apply_runtime_credential_fallbacks();
			$this->assertSame( [], $integration->applied_runtime_credentials );
			$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
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
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'describe_managed_connectors' ] ) );
		$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		$this->assertSame( 'database-secret', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'pre_option_connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'pre_update_option_connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'sanitize_option_connectors_ai_openai_api_key' ) );
	}
}
