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
	 * Credentials that need a runtime override because their provider constant is
	 * invalid or an empty environment variable masks it in the AI Client.
	 *
	 * @var array<string,string>
	 */
	private array $runtime_credential_fallbacks = [];

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
	 * No bundled plugin is required; configure() supplies Core's constants and filters.
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

		foreach ( self::CONNECTORS as $connector_id => $connector ) {
			$this->make_database_credential_inert( $connector['option'] );

			$credential = $this->resolve_credential( $connector );
			if ( null === $credential ) {
				continue;
			}

			// Match Core's credential-source checks exactly. A whitespace-only
			// environment variable is still an explicit external credential, while a
			// constant must be a non-empty string.
			$environment_credential = getenv( $connector['env'] );
			if ( false !== $environment_credential && '' !== $environment_credential ) {
				continue;
			}

			if ( defined( $connector['constant'] ) ) {
				$constant_credential = constant( $connector['constant'] );
				if ( is_string( $constant_credential ) && '' !== $constant_credential ) {
					$credential = $constant_credential;
				} else {
					// PHP constants cannot be redefined. Apply the managed credential to the
					// AI Client registry after Core registers its providers instead.
					$this->runtime_credential_fallbacks[ $connector_id ] = $credential;
					continue;
				}
			} else {
				define( $connector['constant'], $credential );
			}

			// Core ignores empty environment variables, but the AI Client uses them
			// instead of the constant. Hand off the credential Core selected explicitly.
			if ( '' === $environment_credential ) {
				$this->runtime_credential_fallbacks[ $connector_id ] = $credential;
			}
		}

		if ( [] !== $this->runtime_credential_fallbacks ) {
			add_action( 'wp_connectors_init', [ $this, 'apply_runtime_credential_fallbacks' ] );
		}

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
	 * Apply credentials that the AI Client cannot resolve from provider constants.
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

		$registry->setProviderRequestAuthentication( $connector_id, new ApiKeyRequestAuthentication( $credential ) );
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
	 * Core already reports environment variables and constants as external sources.
	 * The fallback below covers the active-but-unconfigured state without exposing
	 * the inert database value.
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
