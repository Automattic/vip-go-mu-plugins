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
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
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

	public function __construct( ?string $name = null, array $data = [], $data_name = '' ) {
		parent::__construct( $name, $data, $data_name );

		// Without the Connectors API, setUp() skips these tests. Opt out of process isolation before
		// PHPUnit applies `@runInSeparateProcess`, so it doesn't boot WordPress again only to skip.
		if ( ! function_exists( '\\wp_get_connectors' ) ) {
			$this->setRunTestInSeparateProcess( false );
		}
	}

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
		// Hooks and options are restored by the test case itself; the process environment and mocked constants are not.
		foreach ( $this->previous_environment_credentials as $environment_name => $credential ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Restore the test process environment.
			putenv( false === $credential ? $environment_name : "{$environment_name}={$credential}" );
		}

		parent::tearDown();
	}

	private function create_recording_integration(): ConnectorControlsIntegration {
		return new class( 'connector-controls' ) extends ConnectorControlsIntegration {
			/** @var array<string,string> */
			public array $applied_runtime_credentials = [];

			protected function set_runtime_credential( string $connector_id, string $credential ): void {
				$this->applied_runtime_credentials[ $connector_id ] = $credential;
			}
		};
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
		// A managed credential with no external sources is covered by test_managed_credentials_define_constants_without_runtime_fallbacks().
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

			$integration = new ConnectorControlsIntegration( 'connector-controls' );
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
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_vip_config_environment_credential_is_applied_before_connector_metadata_is_described(): void {
		require_once __DIR__ . '/../../lib/helpers/environment.php';
		Constant_Mocker::define( 'VIP_ENV_VAR_OPENAI_API_KEY', 'customer-environment-secret' );

		$this->with_test_registry( function ( $registry, $connector_registry, $provider_class ) {
			$integration = new ConnectorControlsIntegration( 'connector-controls' );
			$integration->activate( [ 'config' => [ 'openai_api_key' => 'managed-test-secret' ] ] );
			$integration->configure();
			$this->expose_mocked_constant_to_ai_client();
			$registry->registerProvider( $provider_class );
			\_wp_connectors_register_default_ai_providers( $connector_registry );
			$original_connector = $connector_registry->get_registered( 'openai' );

			$this->assertSame( PHP_INT_MAX - 1, has_action( 'wp_connectors_init', 'Automattic\\VIP\\Helpers\\update_ai_connectors' ) );
			$this->assertFalse( has_action( 'init', 'Automattic\\VIP\\Helpers\\update_ai_connectors' ) );
			$this->assertSame( 'customer-environment-secret', \vip_get_env_var( 'OPENAI_API_KEY' ) );
			$this->assertSame( 'managed-test-secret', $registry->getProviderRequestAuthentication( 'openai' )->getApiKey() );

			do_action( 'wp_connectors_init', $connector_registry );

			$authentication = $registry->getProviderRequestAuthentication( 'openai' );
			$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $authentication );
			$this->assertSame( 'customer-environment-secret', $authentication->getApiKey() );
			$this->assertSame( 'customer-environment-secret', getenv( 'OPENAI_API_KEY' ) );
			$this->assertStringEndsWith(
				'An environment variable supplies this credential and takes precedence over VIP Integration Center.',
				$connector_registry->get_registered( 'openai' )['description']
			);
			$this->assertSame( [
				'source' => 'environment_variable',
				'status' => 'configured',
			], $integration->get_runtime_status()['providers']['openai'] );
			$this->assertStringNotContainsString( 'customer-environment-secret', $connector_registry->get_registered( 'openai' )['description'] );
			$this->assertStringStartsWith( $original_connector['description'], $connector_registry->get_registered( 'openai' )['description'] );
		} );
	}

	/**
	 * The real OPENAI_API_KEY constant can be defined only once per process, so each data set groups the cases that
	 * expose the same constant value. They share one separate process and reset all other state between cases.
	 *
	 * @dataProvider runtime_status_provider
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_runtime_status_observes_authentication_without_exposing_credentials( array $cases ): void {
		$hooks = $this->snapshot_hooks();
		foreach ( $cases as $case => $arguments ) {
			try {
				$this->assert_runtime_status( $case, true, ...$arguments );
			} catch ( ExpectationFailedException $failure ) {
				// Report the failing case and diff as text: compared connectors hold closures, which can't be serialized out of the separate process.
				$comparison = $failure->getComparisonFailure();
				throw new AssertionFailedError( $failure->getMessage() . ( $comparison ? $comparison->getDiff() : '' ) );
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Reset the case's environment credential.
			putenv( 'OPENAI_API_KEY' );
			Constant_Mocker::clear();
			$this->restore_hooks( $hooks );
		}
	}

	/**
	 * Same as above, for cases that never define a real OPENAI_API_KEY constant, so they can share the test process.
	 *
	 * @dataProvider runtime_status_without_constant_provider
	 */
	public function test_runtime_status_observes_authentication_without_a_constant( $environment, $constant, bool $managed, string $expected_source, bool $blocked = false ): void {
		$this->assert_runtime_status( (string) $this->dataName(), false, $environment, $constant, $managed, $expected_source, $blocked );
	}

	private function assert_runtime_status( string $case, bool $isolated, $environment, $constant, bool $managed, string $expected_source, bool $blocked = false ): void {
		if ( false !== $environment ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Isolate external credential sources.
			putenv( "OPENAI_API_KEY={$environment}" );
		}
		if ( null !== $constant ) {
			Constant_Mocker::define( 'OPENAI_API_KEY', $constant );
		}

		$this->with_test_registry( function ( $registry, $connector_registry, $provider_class ) use ( $case, $managed, $expected_source, $blocked, $isolated ) {
			$integration = new ConnectorControlsIntegration( 'connector-controls' );
			$config      = $managed ? [ 'openai_api_key' => 'managed-test-secret' ] : [];
			if ( $blocked ) {
				$config['openai_blocked'] = 'true';
			}
			$integration->activate( [ 'config' => $config ] );
			$integration->configure();
			$this->expose_mocked_constant_to_ai_client( $isolated, $case );
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
			$this->assertSame( $original_connector, $connector_registry->get_registered( 'openai' ), "{$case}: Only the description changes; preserve authentication, logo, and plugin metadata." );
			$this->assertSame( $custom_connector, $connector_registry->get_registered( 'custom' ), $case );
			$this->assertStringEndsWith( 'Manage credentials in VIP Integration Center. The AI provider is unavailable.', $connector_registry->get_registered( 'anthropic' )['description'], $case );
			$this->assertStringEndsWith( 'Manage credentials in VIP Integration Center. The AI provider is unavailable.', $connector_registry->get_registered( 'google' )['description'], $case );

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
			$this->assertSame( $expected, $integration->get_runtime_status(), $case );
			$this->assertSame( $expected, $integration->get_runtime_status(), "{$case}: Repeated reports must remain stable for SDS hashing." );

			// Observe later overrides, not just the credential originally selected.
			$registry->setProviderRequestAuthentication( 'openai', new ApiKeyRequestAuthentication( 'later-custom-secret' ) );
			$this->assertSame( [
				'source' => 'unknown',
				'status' => 'configured',
			], $integration->get_runtime_status()['providers']['openai'], $case );
			$registry->setProviderRequestAuthentication( 'openai', new ApiKeyRequestAuthentication( '' ) );
			$this->assertSame( [
				'source' => 'none',
				'status' => 'unconfigured',
			], $integration->get_runtime_status()['providers']['openai'], $case );
		}, false );
	}

	/**
	 * Cases grouped by the OPENAI_API_KEY constant value they expose: an existing constant, or the managed one.
	 */
	public function runtime_status_provider(): iterable {
		yield 'managed constant' => [
			[
				'managed' => [ false, null, true, 'integration' ],
				'preexisting constant equal to managed remains external' => [ false, 'managed-test-secret', true, 'php_constant' ],
				'empty environment falls through to managed constant' => [ '', null, true, 'integration' ],
			],
		];
		yield 'customer constant' => [
			[
				'environment overrides managed and constant' => [ 'environment-test-secret', 'constant-test-secret', true, 'environment_variable' ],
				'constant overrides managed'  => [ false, 'constant-test-secret', true, 'php_constant' ],
				'empty environment falls through to constant' => [ '', 'constant-test-secret', true, 'php_constant' ],
				'constant without assignment' => [ false, 'constant-test-secret', false, 'php_constant' ],
				'empty environment with constant without assignment' => [ '', 'constant-test-secret', false, 'php_constant' ],
				'empty environment with constant and blocked managed assignment' => [ '', 'constant-test-secret', false, 'php_constant', true ],
			],
		];
		yield 'empty constant' => [ [ 'empty externals fall through to managed' => [ '', '', true, 'integration' ] ] ];
		yield 'invalid constant' => [ [ 'invalid existing constant uses managed fallback' => [ false, false, true, 'integration' ] ] ];
		yield 'whitespace constant' => [ [ 'whitespace constant remains external' => [ false, '   ', true, 'php_constant' ] ] ];
		yield 'zero constant' => [ [ 'zero constant remains external' => [ false, '0', true, 'php_constant' ] ] ];
	}

	/**
	 * A non-empty environment variable wins, or there is no credential, so configure() defines no constant.
	 */
	public function runtime_status_without_constant_provider(): iterable {
		yield 'whitespace environment remains external' => [ '   ', null, true, 'environment_variable' ];
		yield 'zero environment remains external' => [ '0', null, true, 'environment_variable' ];
		yield 'environment without assignment' => [ 'environment-test-secret', null, false, 'environment_variable' ];
		yield 'no credential' => [ false, null, false, 'none' ];
		yield 'empty environment without assignment' => [ '', null, false, 'none' ];
	}

	/**
	 * The integration's namespace mocks constant functions. Mirror its selected
	 * constant into the isolated PHP process so the unmodified AI Client performs
	 * its real environment/constant discovery, rather than injecting test auth.
	 */
	private function expose_mocked_constant_to_ai_client( bool $isolated = true, string $case = '' ): void {
		if ( ! Constant_Mocker::defined( 'OPENAI_API_KEY' ) ) {
			$this->assertFalse( \defined( 'OPENAI_API_KEY' ), "{$case}: A real OPENAI_API_KEY from an earlier case would leak into this one." );
			return;
		}

		$this->assertTrue( $isolated, 'Cases that define OPENAI_API_KEY must run in a separate process.' );
		if ( ! \defined( 'OPENAI_API_KEY' ) ) {
			\define( 'OPENAI_API_KEY', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		}
		// The real constant cannot be redefined, so cases sharing a process must expose the same value.
		$this->assertSame( Constant_Mocker::constant( 'OPENAI_API_KEY' ), \constant( 'OPENAI_API_KEY' ), $case );
	}

	/**
	 * Copy the hook globals, like WP_UnitTestCase_Base::_backup_hooks(), to reset them between cases of one test.
	 */
	private function snapshot_hooks(): array {
		$hooks = [ 'wp_filter' => [] ];
		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook ) {
			$hooks['wp_filter'][ $hook_name ] = clone $hook;
		}
		foreach ( [ 'wp_actions', 'wp_filters', 'wp_current_filter' ] as $key ) {
			$hooks[ $key ] = $GLOBALS[ $key ];
		}

		return $hooks;
	}

	private function restore_hooks( array $hooks ): void {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the snapshot between cases.
		$GLOBALS['wp_filter'] = [];
		foreach ( $hooks['wp_filter'] as $hook_name => $hook ) {
			$GLOBALS['wp_filter'][ $hook_name ] = clone $hook;
		}
		foreach ( [ 'wp_actions', 'wp_filters', 'wp_current_filter' ] as $key ) {
			$GLOBALS[ $key ] = $hooks[ $key ];
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
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

	public function test_inactive_runtime_report_is_omitted_and_does_not_initialize_the_ai_client(): void {
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$this->assertNull( $integration->get_runtime_status() );
	}

	public function test_switched_blog_does_not_report_another_blogs_authentication(): void {
		$this->skipWithoutMultisite();
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();

		switch_to_blog( $this->factory()->blog->create() );

		$this->assertNull( $integration->get_runtime_status() );
	}

	/**
	 * The most specific level that sets or blocks a credential wins: network site, environment, then an enabled organization.
	 *
	 * @dataProvider credential_inheritance_provider
	 */
	public function test_credential_inheritance_across_config_levels( string $org_status, array $env_config, ?array $network_site_config, array $expected_constants ): void {
		$vip_config = [
			'org' => [
				'status' => $org_status,
				'config' => [ 'openai_api_key' => 'org-secret' ],
			],
			'env' => [
				'status' => Env_Integration_Status::ENABLED,
				'config' => $env_config,
			],
		];
		if ( null !== $network_site_config ) {
			$this->skipWithoutMultisite();
			$blog_id = $this->factory()->blog->create();
			switch_to_blog( $blog_id );
			$vip_config['network_sites'] = [
				$blog_id => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => $network_site_config,
				],
			];
		}
		$integration = $this->create_recording_integration();
		$integration->set_vip_config( new IntegrationVipConfig( 'connector-controls', $vip_config ) );
		$integration->activate();

		$integration->configure();
		$integration->apply_runtime_credential_fallbacks();

		foreach ( $expected_constants as $constant_name => $credential ) {
			if ( null === $credential ) {
				$this->assertFalse( Constant_Mocker::defined( $constant_name ), $constant_name );
			} else {
				$this->assertSame( $credential, Constant_Mocker::constant( $constant_name ), $constant_name );
			}
		}
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
		$this->assertTrue( $integration->is_active() );
		$this->assertTrue( $integration->is_loaded() );
	}

	public function credential_inheritance_provider(): iterable {
		$environment_credential = [ 'openai_api_key' => 'environment-secret' ];

		yield 'environment inherits the organization credential when empty' => [ Org_Integration_Status::ENABLED, [ 'openai_api_key' => '' ], null, [ 'OPENAI_API_KEY' => 'org-secret' ] ];
		yield 'environment credential overrides the organization' => [ Org_Integration_Status::ENABLED, $environment_credential, null, [ 'OPENAI_API_KEY' => 'environment-secret' ] ];
		yield 'environment can block an organization credential' => [ Org_Integration_Status::ENABLED, [ 'openai_blocked' => 'true' ], null, [ 'OPENAI_API_KEY' => null ] ];
		yield 'network site credential overrides the environment' => [ Org_Integration_Status::ENABLED, $environment_credential, [ 'openai_api_key' => 'network-secret' ], [ 'OPENAI_API_KEY' => 'network-secret' ] ];
		yield 'network site can block an environment credential' => [ Org_Integration_Status::ENABLED, $environment_credential, [ 'openai_blocked' => 'true' ], [ 'OPENAI_API_KEY' => null ] ];
		yield 'disabled organization allows network site credentials without inheriting its own' => [
			Org_Integration_Status::DISABLED,
			[ 'network_wide_enable' => 'false' ],
			[ 'anthropic_api_key' => 'network-secret' ],
			[
				'ANTHROPIC_API_KEY' => 'network-secret',
				'OPENAI_API_KEY'    => null,
			],
		];
	}

	public function test_disabled_organization_allows_environment_credentials_without_inheriting_organization_credentials(): void {
		update_option( 'connectors_ai_openai_api_key', 'database-secret' );
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::DISABLED,
					'config' => [
						'openai_api_key'    => 'org-secret',
						'anthropic_api_key' => 'org-anthropic-secret',
					],
				],
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'environment-secret' ],
				],
			]
		);
		$integration = $this->create_recording_integration();
		$integration->set_vip_config( $config );
		$integration->activate();

		$integration->configure();

		$integration->apply_runtime_credential_fallbacks();
		$this->assertSame( 'environment-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		$this->assertFalse( Constant_Mocker::defined( 'ANTHROPIC_API_KEY' ) );
		$this->assertSame( [], $integration->applied_runtime_credentials );
		$this->assertFalse( has_action( 'wp_connectors_init', [ $integration, 'apply_runtime_credential_fallbacks' ] ) );
		$this->assertSame( PHP_INT_MAX, has_filter( 'pre_option', [ $integration, 'filter_pre_option' ] ) );
		$this->assertSame( PHP_INT_MAX, has_filter( 'pre_update_option', [ $integration, 'filter_pre_update_option' ] ) );
		$this->assertSame( '', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertFalse( update_option( 'connectors_ai_openai_api_key', 'replacement-secret' ) );
		$this->assertSame( '', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertTrue( $integration->is_active() );
		$this->assertTrue( $integration->is_loaded() );
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
		remove_filter( 'pre_option', [ $integration, 'filter_pre_option' ], PHP_INT_MAX );
		$this->assertSame( 'database-secret', get_option( 'connectors_ai_openai_api_key' ) );
	}

	/**
	 * @dataProvider global_read_filter_provider
	 */
	public function test_global_read_filters_cannot_expose_managed_database_credentials( string $option_name, $filtered_value ): void {
		update_option( $option_name, 'database-secret' );
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		add_filter(
			'pre_option',
			static function ( $pre, $option ) use ( $option_name, $filtered_value ) {
				return $option_name === $option ? $filtered_value : $pre;
			},
			100,
			2
		);

		$this->assertSame( '', get_option( $option_name ) );
	}

	public function global_read_filter_provider(): iterable {
		foreach ( self::OPTIONS as $option_name ) {
			yield "{$option_name}: injected credential" => [ $option_name, 'filtered-secret' ];
			yield "{$option_name}: database fallback" => [ $option_name, false ];
		}
	}

	/**
	 * @dataProvider managed_database_option_provider
	 */
	public function test_global_write_filters_cannot_replace_managed_database_credentials( string $option_name ): void {
		update_option( $option_name, 'database-secret' );
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		add_filter(
			'pre_update_option',
			static function ( $value, $option ) use ( $option_name ) {
				return $option_name === $option ? 'filtered-secret' : $value;
			},
			100,
			2
		);

		$this->assertFalse( update_option( $option_name, 'replacement-secret' ) );
		remove_all_filters( "pre_option_{$option_name}" );
		remove_filter( 'pre_option', [ $integration, 'filter_pre_option' ], PHP_INT_MAX );
		$this->assertSame( 'database-secret', get_option( $option_name ) );
	}

	public function managed_database_option_provider(): iterable {
		foreach ( self::OPTIONS as $option_name ) {
			yield $option_name => [ $option_name ];
		}
	}

	/**
	 * @dataProvider unrelated_database_option_provider
	 */
	public function test_global_guards_preserve_unrelated_option_reads_writes_and_filters( string $option_name ): void {
		update_option( $option_name, 'original-value' );
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		$read_filter  = static function ( $pre, $option ) use ( $option_name ) {
			return $option_name === $option ? 'filtered-read' : $pre;
		};
		$write_filter = static function ( $value, $option ) use ( $option_name ) {
			return $option_name === $option ? 'filtered-write' : $value;
		};

		$this->assertSame( 'original-value', get_option( $option_name ) );
		$this->assertTrue( update_option( $option_name, 'replacement-value' ) );
		$this->assertSame( 'replacement-value', get_option( $option_name ) );

		add_filter( 'pre_option', $read_filter, 100, 2 );
		$this->assertSame( 'filtered-read', get_option( $option_name ) );
		remove_filter( 'pre_option', $read_filter, 100 );

		add_filter( 'pre_update_option', $write_filter, 100, 2 );
		$this->assertTrue( update_option( $option_name, 'replacement-value-2' ) );
		$this->assertSame( 'filtered-write', get_option( $option_name ) );
	}

	public function unrelated_database_option_provider(): iterable {
		yield 'ordinary option' => [ 'connector_controls_unrelated_option' ];
		yield 'unmanaged connector' => [ 'connectors_ai_custom_api_key' ];
		yield 'managed option name prefix' => [ 'connectors_ai_openai_api_key_suffix' ];
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
		remove_filter( 'pre_option', [ $integration, 'filter_pre_option' ], PHP_INT_MAX );
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
		$this->assertFalse( has_filter( 'pre_option', [ $integration, 'filter_pre_option' ] ) );
		$this->assertFalse( has_filter( 'pre_update_option', [ $integration, 'filter_pre_update_option' ] ) );
		$this->assertTrue( update_option( 'connectors_ai_openai_api_key', 'replacement-secret' ) );
		$this->assertSame( 'replacement-secret', get_option( 'connectors_ai_openai_api_key' ) );
	}
}
