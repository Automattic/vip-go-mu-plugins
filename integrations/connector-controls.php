<?php
/**
 * Integration: Connector Controls.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Supplies centrally managed credentials to WordPress Core connectors.
 *
 * @private
 */
class ConnectorControlsIntegration extends Integration {
	/**
	 * Credentials requiring a fallback because the AI Client cannot use the constant.
	 *
	 * @var array<string,string>
	 */
	private array $runtime_credential_fallbacks = [];

	/** @var array<string,string> Provider constants created from managed credentials. */
	private array $managed_constants = [];

	/** @var array<string,string> Sources selected for runtime credential fallbacks. */
	private array $runtime_credential_sources = [];

	/** @var array<string,ApiKeyRequestAuthentication> Authentication actually installed by this integration. */
	private array $runtime_authentications = [];

	/** @var int|null Blog whose credentials were configured in this PHP request. */
	private ?int $configured_blog_id = null;

	/**
	 * Managed connector definitions.
	 *
	 * @var array<string,array{config_key:string,blocked_key:string,constant:string,env:string,option:string}>
	 */
	private const CONNECTORS = [
		'openai'    => [
			'config_key'  => 'openai_api_key',
			'blocked_key' => 'openai_blocked',
			'constant'    => 'OPENAI_API_KEY',
			'env'         => 'OPENAI_API_KEY',
			'option'      => 'connectors_ai_openai_api_key',
		],
		'anthropic' => [
			'config_key'  => 'anthropic_api_key',
			'blocked_key' => 'anthropic_blocked',
			'constant'    => 'ANTHROPIC_API_KEY',
			'env'         => 'ANTHROPIC_API_KEY',
			'option'      => 'connectors_ai_anthropic_api_key',
		],
		'google'    => [
			'config_key'  => 'google_api_key',
			'blocked_key' => 'google_blocked',
			'constant'    => 'GOOGLE_API_KEY',
			'env'         => 'GOOGLE_API_KEY',
			'option'      => 'connectors_ai_google_api_key',
		],
	];

	/**
	 * Enable Pendo tracking for this integration.
	 *
	 * @var bool
	 */
	protected bool $enable_pendo_tracking = true;

	/**
	 * Whether the integration has configured the runtime.
	 */
	public function is_loaded(): bool {
		return defined( 'VIP_CONNECTOR_CONTROLS_LOADED' );
	}

	/**
	 * No bundled plugin is required; configure() supplies provider constants and filters.
	 */
	public function load(): void {}

	/**
	 * Resolve the most-specific platform credentials and lock Core's local stores.
	 */
	public function configure(): void {
		if ( ! $this->is_enabled_for_org() || ! $this->is_connectors_api_available() ) {
			$this->is_active = false;
			return;
		}

		$this->configured_blog_id = get_current_blog_id();

		// Core applies these global filters after the option-specific filters.
		add_filter( 'pre_option', [ $this, 'filter_pre_option' ], PHP_INT_MAX, 2 );
		add_filter( 'pre_update_option', [ $this, 'filter_pre_update_option' ], PHP_INT_MAX, 3 );

		foreach ( self::CONNECTORS as $connector_id => $connector ) {
			$this->make_database_credential_inert( $connector['option'] );

			$credential = $this->resolve_credential( $connector );

			// Non-empty environment variables take precedence in both Core and the
			// AI Client. Leave their existing authentication path unchanged.
			$environment_credential = getenv( $connector['env'] );
			if ( false !== $environment_credential && '' !== $environment_credential ) {
				continue;
			}

			$source = 'integration';
			if ( defined( $connector['constant'] ) ) {
				$constant_credential = constant( $connector['constant'] );
				if ( is_string( $constant_credential ) && '' !== $constant_credential ) {
					$credential = $constant_credential;
					$source     = isset( $this->managed_constants[ $connector_id ] ) && $this->managed_constants[ $connector_id ] === $credential
						? 'integration'
						: 'php_constant';
				} else {
					if ( null === $credential ) {
						continue;
					}
					// An empty or invalid constant cannot be redefined. Preserve it
					// and supply the managed credential through the runtime fallback.
					$this->runtime_credential_fallbacks[ $connector_id ] = $credential;
					$this->runtime_credential_sources[ $connector_id ]   = $source;
					continue;
				}
			} else {
				if ( null === $credential ) {
					continue;
				}
				define( $connector['constant'], $credential );
				$this->managed_constants[ $connector_id ] = $credential;
			}

			// Core ignores an empty environment variable, but the AI Client lets
			// it mask the constant. Explicitly supply the selected constant value.
			if ( '' === $environment_credential ) {
				$this->runtime_credential_fallbacks[ $connector_id ] = $credential;
				$this->runtime_credential_sources[ $connector_id ]   = $source;
			}
		}

		if ( [] !== $this->runtime_credential_fallbacks ) {
			add_action( 'wp_connectors_init', [ $this, 'apply_runtime_credential_fallbacks' ] );
		}

		add_action( 'wp_connectors_init', [ $this, 'describe_managed_connectors' ], PHP_INT_MAX );
		add_filter( 'script_module_data_options-connectors-wp-admin', [ $this, 'lock_connector_fields' ], PHP_INT_MAX );

		if ( ! defined( 'VIP_CONNECTOR_CONTROLS_LOADED' ) ) {
			define( 'VIP_CONNECTOR_CONTROLS_LOADED', true );
		}
	}

	/**
	 * Whether the WordPress Connectors API is available.
	 */
	protected function is_connectors_api_available(): bool {
		return function_exists( '\\wp_get_connectors' );
	}

	/**
	 * Apply credentials the AI Client could not resolve from provider constants.
	 */
	public function apply_runtime_credential_fallbacks(): void {
		foreach ( $this->runtime_credential_fallbacks as $connector_id => $credential ) {
			$this->set_runtime_credential( $connector_id, $credential );
		}
	}

	/**
	 * Configure one AI provider directly in its runtime registry.
	 */
	protected function set_runtime_credential( string $connector_id, string $credential ): void {
		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( $connector_id ) ) {
			return;
		}

		$authentication = new ApiKeyRequestAuthentication( $credential );
		$registry->setProviderRequestAuthentication( $connector_id, $authentication );
		$this->runtime_authentications[ $connector_id ] = $authentication;
	}

	/**
	 * Observe the AI Client's current authentication without returning credentials.
	 *
	 * This describes the registry in this request, not provider-side key validity.
	 * It performs no network requests and uses SDS's existing report timestamp.
	 * A switched blog does not have its own initialized registry in this request.
	 *
	 * @return array|null Safe runtime metadata, or null when disabled or the blog context changed.
	 */
	public function get_runtime_status(): ?array {
		if ( null === $this->configured_blog_id ) {
			return null;
		}

		if ( get_current_blog_id() !== $this->configured_blog_id ) {
			return null;
		}

		$report = [
			'active'    => true,
			'providers' => array_fill_keys( array_keys( self::CONNECTORS ), [
				'source' => 'unknown',
				'status' => 'unknown',
			] ),
		];
		if ( ! did_action( 'wp_connectors_init' ) || ! class_exists( AiClient::class ) ) {
			return $report;
		}

		$registry = AiClient::defaultRegistry();
		foreach ( self::CONNECTORS as $connector_id => $connector ) {
			if ( ! $registry->hasProvider( $connector_id ) ) {
				$report['providers'][ $connector_id ] = [
					'source' => 'none',
					'status' => 'provider_unavailable',
				];
				continue;
			}

			$authentication = $registry->getProviderRequestAuthentication( $connector_id );
			if ( null === $authentication || ( $authentication instanceof ApiKeyRequestAuthentication && '' === $authentication->getApiKey() ) ) {
				$report['providers'][ $connector_id ] = [
					'source' => 'none',
					'status' => 'unconfigured',
				];
				continue;
			}

			$source = 'unknown';
			if ( isset( $this->runtime_authentications[ $connector_id ] ) && $authentication === $this->runtime_authentications[ $connector_id ] ) {
				$source = $this->runtime_credential_sources[ $connector_id ];
			} elseif ( $authentication instanceof ApiKeyRequestAuthentication ) {
				// Identify the credential actually held by the registry. A constant
				// we created carries an integration credential, not a customer override.
				$credential = $authentication->getApiKey();
				if ( getenv( $connector['env'] ) === $credential ) {
					$source = 'environment_variable';
				} elseif ( defined( $connector['constant'] ) && constant( $connector['constant'] ) === $credential ) {
					$source = isset( $this->managed_constants[ $connector_id ] ) && $this->managed_constants[ $connector_id ] === $credential
						? 'integration'
						: 'php_constant';
				}
			}
			$report['providers'][ $connector_id ] = [
				'source' => $source,
				'status' => 'configured',
			];
		}

		return $report;
	}

	/**
	 * Explain management and the observed credential source on Core's cards.
	 *
	 * Use Core's supported metadata override after credential fallbacks have run.
	 * Keep the provider's metadata and Core's built-in field help unchanged.
	 *
	 * @param \WP_Connector_Registry $registry Initialized connector registry.
	 */
	public function describe_managed_connectors( \WP_Connector_Registry $registry ): void {
		$report = $this->get_runtime_status();
		if ( empty( $report['active'] ) ) {
			return;
		}

		foreach ( $report['providers'] as $connector_id => $provider ) {
			if ( ! $registry->is_registered( $connector_id ) ) {
				continue;
			}

			if ( 'provider_unavailable' === $provider['status'] ) {
				$description = __( 'Manage credentials in VIP Integration Center. The AI provider is unavailable.', 'vip' );
			} elseif ( 'unconfigured' === $provider['status'] ) {
				$description = __( 'No credential is configured. Configure a credential in VIP Integration Center.', 'vip' );
			} else {
				switch ( $provider['source'] ) {
					case 'integration':
						$description = __( 'Credentials managed in VIP Integration Center.', 'vip' );
						break;
					case 'environment_variable':
						$description = __( 'An environment variable supplies this credential and takes precedence over VIP Integration Center.', 'vip' );
						break;
					case 'php_constant':
						$description = __( 'A customer-defined PHP constant supplies this credential and takes precedence over VIP Integration Center.', 'vip' );
						break;
					default:
						$description = __( 'Manage credential assignments in VIP Integration Center. The credential source could not be determined.', 'vip' );
				}
			}

			$connector                = $registry->unregister( $connector_id );
			$connector['description'] = trim( $connector['description'] . ' ' . $description );
			$registry->register( $connector_id, $connector );
		}
	}

	/**
	 * Resolve a credential while respecting explicit opt-outs at more-specific levels.
	 *
	 * @param array{config_key:string,blocked_key:string,constant:string,env:string,option:string} $connector Connector definition.
	 */
	private function resolve_credential( array $connector ): ?string {
		$configs = [];
		if ( is_multisite() ) {
			$configs[] = $this->get_network_site_config();
		}
		$configs[] = $this->get_env_config();
		$configs[] = $this->get_org_config();

		foreach ( $configs as $config ) {
			$blocked = $config[ $connector['blocked_key'] ] ?? false;
			if ( in_array( $blocked, [ true, 1, '1', 'true' ], true ) ) {
				return null;
			}

			$credential = $config[ $connector['config_key'] ] ?? null;
			if ( is_string( $credential ) && '' !== trim( $credential ) ) {
				return $credential;
			}
		}

		return null;
	}

	/**
	 * Keep global option filters from restoring managed database credentials.
	 *
	 * @param mixed  $pre_value   Value from earlier option filters.
	 * @param string $option_name WordPress option name.
	 * @return mixed
	 */
	public function filter_pre_option( $pre_value, string $option_name ) {
		return $this->is_managed_credential_option( $option_name ) ? '' : $pre_value;
	}

	/**
	 * Prevent global option filters from changing managed database credentials.
	 *
	 * @param mixed  $new_value   Value from earlier update filters.
	 * @param string $option_name WordPress option name.
	 * @param mixed  $old_value   Current option value.
	 * @return mixed
	 */
	public function filter_pre_update_option( $new_value, string $option_name, $old_value ) {
		return $this->is_managed_credential_option( $option_name ) ? $old_value : $new_value;
	}

	/**
	 * Whether the option stores a credential controlled by this integration.
	 *
	 * @param string $option_name WordPress option name.
	 */
	private function is_managed_credential_option( string $option_name ): bool {
		foreach ( self::CONNECTORS as $connector ) {
			if ( $connector['option'] === $option_name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep managed database credentials inert and prevent credential writes.
	 *
	 * @param string $option_name WordPress option name.
	 */
	private function make_database_credential_inert( string $option_name ): void {
		add_filter( "pre_option_{$option_name}", '__return_empty_string', PHP_INT_MAX );
		// add_option() can skip get_option() when a missing option is cached, and
		// has no short-circuit filter. Ensure any inserted value contains no key.
		add_filter( "sanitize_option_{$option_name}", '__return_empty_string', PHP_INT_MAX );
		add_filter(
			"pre_update_option_{$option_name}",
			static function ( $new_value, $old_value ) {
				return $old_value;
			},
			PHP_INT_MAX,
			2
		);
	}

	/**
	 * Mark managed fields read-only even when a credential has been revoked.
	 *
	 * Core recognizes provider constants and environment variables as read-only.
	 * Its constant marker also locks fields with no credential or a runtime
	 * fallback, without exposing inert database values.
	 *
	 * @param array<string,mixed> $data Connector screen script-module data.
	 * @return array<string,mixed>
	 */
	public function lock_connector_fields( array $data ): array {
		if ( ! isset( $data['connectors'] ) || ! is_array( $data['connectors'] ) ) {
			return $data;
		}

		foreach ( array_keys( self::CONNECTORS ) as $connector_id ) {
			if ( ! isset( $data['connectors'][ $connector_id ]['authentication'] ) ) {
				continue;
			}

			$key_source = $data['connectors'][ $connector_id ]['authentication']['keySource'] ?? 'none';
			if ( ! in_array( $key_source, [ 'env', 'constant' ], true ) ) {
				$data['connectors'][ $connector_id ]['authentication']['keySource'] = 'constant';
			}
		}

		return $data;
	}
}
