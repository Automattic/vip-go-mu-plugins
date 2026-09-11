<?php
/**
 * Integration: Connector Controls.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Supplies centrally managed credentials to WordPress Core connectors.
 *
 * @private
 */
class ConnectorControlsIntegration extends Integration {
	/**
	 * Managed connector definitions.
	 *
	 * @var array<string,array{config_key:string,constant:string,env:string,option:string}>
	 */
	private const CONNECTORS = [
		'openai'    => [
			'config_key' => 'openai_api_key',
			'constant'   => 'OPENAI_API_KEY',
			'env'        => 'OPENAI_API_KEY',
			'option'     => 'connectors_ai_openai_api_key',
		],
		'anthropic' => [
			'config_key' => 'anthropic_api_key',
			'constant'   => 'ANTHROPIC_API_KEY',
			'env'        => 'ANTHROPIC_API_KEY',
			'option'     => 'connectors_ai_anthropic_api_key',
		],
		'google'    => [
			'config_key' => 'google_api_key',
			'constant'   => 'GOOGLE_API_KEY',
			'env'        => 'GOOGLE_API_KEY',
			'option'     => 'connectors_ai_google_api_key',
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
		if ( ! $this->is_enabled_for_org() ) {
			$this->is_active = false;
			return;
		}

		$config = array_replace( $this->get_org_config(), $this->get_env_config() );
		if ( is_multisite() ) {
			$config = array_replace( $config, $this->get_network_site_config() );
		}

		foreach ( self::CONNECTORS as $connector ) {
			$this->make_database_credential_inert( $connector['option'] );

			$credential = $config[ $connector['config_key'] ] ?? null;
			if ( ! is_string( $credential ) || '' === trim( $credential ) ) {
				continue;
			}

			// Core gives environment variables precedence over constants. Preserve either
			// existing source rather than silently replacing customer configuration.
			$environment_credential = getenv( $connector['env'] );
			if ( ( is_string( $environment_credential ) && '' !== trim( $environment_credential ) ) || defined( $connector['constant'] ) ) {
				continue;
			}

			define( $connector['constant'], $credential );
		}

		add_filter( 'script_module_data_options-connectors-wp-admin', [ $this, 'lock_connector_fields' ], 100 );

		if ( ! defined( 'VIP_CONNECTOR_CONTROLS_LOADED' ) ) {
			define( 'VIP_CONNECTOR_CONTROLS_LOADED', true );
		}
	}

	/**
	 * Keep managed database credentials inert and reject option writes.
	 *
	 * @param string $option_name WordPress option name.
	 */
	private function make_database_credential_inert( string $option_name ): void {
		add_filter( "pre_option_{$option_name}", '__return_empty_string' );
		add_filter(
			"pre_update_option_{$option_name}",
			static function ( $new_value, $old_value ) {
				return $old_value;
			},
			10,
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
