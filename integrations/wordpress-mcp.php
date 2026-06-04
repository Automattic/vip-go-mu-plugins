<?php
/**
 * Integration: WordPress MCP.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the WordPress MCP integration.
 *
 * @private
 */
class WordPressMcpIntegration extends Integration {
	private const AUTH_HEADER_SERVER_KEY      = 'HTTP_X_VIP_MCP_AUTH';
	private const TIMESTAMP_HEADER_SERVER_KEY = 'HTTP_X_VIP_MCP_AUTH_TIMESTAMP';
	private const DEFAULT_TIMESTAMP_MAX_AGE   = 120;
	// Mirrors mcp-adapter defaults for auth route detection before the config filter runs.
	private const DEFAULT_SERVER_NAMESPACE = 'mcp';
	private const DEFAULT_SERVER_ROUTE     = 'mcp-adapter-default-server';

	/**
	 * Enable Pendo tracking for this integration.
	 *
	 * @var bool
	 */
	protected bool $enable_pendo_tracking = true;

	public function is_loaded(): bool {
		return class_exists( '\WP\MCP\Plugin', false );
	}

	public function load(): void {
		if ( $this->has_server_config() ) {
			add_filter( 'mcp_adapter_default_server_config', [ $this, 'filter_default_server_config' ], PHP_INT_MAX );
		}

		add_filter( 'determine_current_user', [ $this, 'authenticate_mcp_request' ], 19 );

		add_action( 'plugins_loaded', function () {
			if ( $this->is_loaded() ) {
				return;
			}

			$versions = $this->get_versions();

			if ( empty( $versions ) ) {
				$this->is_active = false;
				return;
			}

			$selected_version_folder = array_key_first( $versions );
			$load_path               = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $selected_version_folder . '/mcp-adapter.php';

			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Pass down integration configuration to the MCP adapter server.
	 *
	 * @param array $config Default server config.
	 * @return mixed Filtered server config.
	 */
	public function filter_default_server_config( $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}

		$server_namespace = $this->get_server_config_value( 'server_namespace' );
		if ( null !== $server_namespace ) {
			$config['server_route_namespace'] = $server_namespace;
		}

		$server_route = $this->get_server_config_value( 'server_route' );
		if ( null !== $server_route ) {
			$config['server_route'] = $server_route;
		}

		return $config;
	}

	/**
	 * Authenticate MCP requests.
	 *
	 * @param int|false|null $input_user Existing authenticated user ID.
	 * @return int|false|null Authenticated user ID, or the original input.
	 */
	public function authenticate_mcp_request( $input_user ) {
		if ( ! empty( $input_user ) ) {
			return $input_user;
		}

		if ( ! $this->is_mcp_adapter_rest_request() ) {
			return $input_user;
		}

		if ( ! defined( 'AUTH_KEY' ) || '' === constant( 'AUTH_KEY' ) ) {
			return $input_user;
		}

		$email         = $this->get_server_value( 'PHP_AUTH_USER' );
		$provided_hash = $this->get_server_value( 'PHP_AUTH_PW' );
		$auth_header   = $this->get_server_value( self::AUTH_HEADER_SERVER_KEY );
		$timestamp     = $this->get_server_value( self::TIMESTAMP_HEADER_SERVER_KEY );

		if ( null === $email || null === $provided_hash || null === $auth_header ) {
			return $input_user;
		}

		if ( 'true' !== strtolower( $auth_header ) ) {
			return $input_user;
		}

		if ( null === $timestamp ) {
			$this->trigger_auth_warning( 'Missing timestamp' );

			return $input_user;
		}

		if ( ! ctype_digit( $timestamp ) ) {
			$this->trigger_auth_warning( 'Invalid timestamp format' );

			return $input_user;
		}

		if ( abs( time() - (int) $timestamp ) > $this->get_timestamp_max_age() ) {
			$this->trigger_auth_warning( 'Timestamp expired' );

			return $input_user;
		}

		if ( ! is_email( $email ) ) {
			$this->trigger_auth_warning( 'Invalid email format in PHP_AUTH_USER header.' );

			return $input_user;
		}

		$expected = hash_hmac( 'sha256', $email . $timestamp, constant( 'AUTH_KEY' ) );

		if ( ! hash_equals( $expected, $provided_hash ) ) {
			$this->trigger_auth_warning( 'Authentication failed' );

			return $input_user;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$this->trigger_auth_warning( 'User not found for email hash ' . hash( 'sha256', strtolower( $email ) ) );

			return $input_user;
		}

		return $user->ID;
	}

	/**
	 * Check whether the current request targets the MCP adapter REST endpoint.
	 */
	public function is_mcp_adapter_rest_request(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! constant( 'REST_REQUEST' ) ) {
			return false;
		}

		if ( 'POST' !== $this->get_server_value( 'REQUEST_METHOD' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST route detection only.
		if ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST route detection only.
			$rest_route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );

			return untrailingslashit( $rest_route ) === $this->get_adapter_rest_route();
		}

		$request_uri = $this->get_server_value( 'REQUEST_URI' );
		if ( null === $request_uri ) {
			return false;
		}

		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return false;
		}

		$rest_path = '/' . rest_get_url_prefix() . $this->get_adapter_rest_route();

		return str_ends_with( untrailingslashit( $path ), $rest_path );
	}

	/**
	 * Get the adapter REST route for the configured server.
	 */
	private function get_adapter_rest_route(): string {
		$server_namespace = $this->get_server_config_value( 'server_namespace' ) ?? self::DEFAULT_SERVER_NAMESPACE;
		$server_route     = $this->get_server_config_value( 'server_route' ) ?? self::DEFAULT_SERVER_ROUTE;

		return '/' . $server_namespace . '/' . $server_route;
	}

	/**
	 * Whether VIP config provided any adapter server config.
	 */
	private function has_server_config(): bool {
		return null !== $this->get_server_config_value( 'server_namespace' ) || null !== $this->get_server_config_value( 'server_route' );
	}

	/**
	 * Get a non-empty string server config value.
	 */
	private function get_server_config_value( string $key ): ?string {
		$server_configs = array_merge(
			$this->get_env_config(),
			is_multisite() ? $this->get_network_site_config() : []
		);

		if ( ! isset( $server_configs[ $key ] ) || ! is_string( $server_configs[ $key ] ) || '' === $server_configs[ $key ] ) {
			return null;
		}

		return $server_configs[ $key ];
	}

	/**
	 * Read a scalar value from $_SERVER.
	 */
	private function get_server_value( string $key ): ?string {
		if ( ! isset( $_SERVER[ $key ] ) || ! is_scalar( $_SERVER[ $key ] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
	}

	/**
	 * Get the configured maximum age for the HMAC timestamp.
	 */
	private function get_timestamp_max_age(): int {
		if ( defined( 'VIP_MCP_AUTH_TIMESTAMP_MAX_AGE' ) ) {
			return max( 1, (int) constant( 'VIP_MCP_AUTH_TIMESTAMP_MAX_AGE' ) );
		}

		return self::DEFAULT_TIMESTAMP_MAX_AGE;
	}

	/**
	 * Emit an authentication warning without exposing raw user identity.
	 */
	private function trigger_auth_warning( string $message ): void {
		trigger_error( esc_html( 'VIP MCP Auth: ' . $message ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	}

	/**
	 * Get the available versions of WordPress MCP in descending order.
	 *
	 * @return array<string,string>
	 */
	public function get_versions(): array {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'wordpress-mcp', 'mcp-adapter.php' );
	}
}
