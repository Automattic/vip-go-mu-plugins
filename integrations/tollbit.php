<?php
/**
 * Integration: Tollbit.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Tollbit VIP Integration.
 */
class TollbitIntegration extends Integration {
	/**
	 * Since Tollbit is only available through integrations and not customer code, it's never "already loaded"
	 */
	public function is_loaded(): bool {
		return false;
	}

	/**
	 * Loads the integration.
	 */
	public function load(): void {
		// The functionality is implemented directly in this integration
		// No external plugin loading needed
	}

	/**
	 * Configure `Tollbit` for VIP Platform.
	 */
	public function configure(): void {
		add_action( 'init', [ $this, 'handle_ai_bot_redirect' ], PHP_INT_MAX );

		add_action( 'init', [ $this, 'setup_cache_segmentation' ], PHP_INT_MAX );
	}

	/**
	 * Handle redirect for AI bots based on X-AI-Bot header.
	 */
	public function handle_ai_bot_redirect(): void {
		if ( ! isset( $_SERVER['HTTP_X_AI_BOT'] ) ) {
			return;
		}
		
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! isset( $_SERVER['HTTP_HOST'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$redirect_url = $this->create_tollbit_url( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		if ( ! $redirect_url ) {
			return;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( esc_url_raw( $redirect_url ), 307 );
		exit;
	}

	/**
	 * Create a tollbit subdomain URL from the current URL.
	 *
	 * @param string $current_url The current URL to transform.
	 * 
	 * @return string|null The tollbit subdomain URL or null if creation failed.
	 */
	private function create_tollbit_url( string $current_url ): ?string {
		$parsed_url = wp_parse_url( $current_url );
		
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			return null;
		}

		$host = $parsed_url['host'];
		
		// Check if it's already a tollbit subdomain to avoid infinite redirects
		if ( strpos( $host, 'tollbit.' ) === 0 ) {
			return null;
		}

		$tollbit_host = 'tollbit.' . $host;
		$tollbit_url  = $parsed_url['scheme'] . '://' . $tollbit_host;
		
		if ( isset( $parsed_url['port'] ) ) {
			$tollbit_url .= ':' . $parsed_url['port'];
		}
		
		if ( isset( $parsed_url['path'] ) ) {
			$tollbit_url .= $parsed_url['path'];
		}
		
		if ( isset( $parsed_url['query'] ) ) {
			$tollbit_url .= '?' . $parsed_url['query'];
		}
		
		if ( isset( $parsed_url['fragment'] ) ) {
			$tollbit_url .= '#' . $parsed_url['fragment'];
		}

		return $tollbit_url;
	}

	/**
	 * Set up cache segmentation for AI bots.
	 */
	public function setup_cache_segmentation(): void {
		if ( ! isset( $_SERVER['HTTP_X_AI_BOT'] ) ) {
			return;
		}

		if ( ! class_exists( 'Automattic\VIP\Cache\Vary_Cache' ) ) {
			return;
		}

		\Automattic\VIP\Cache\Vary_Cache::register_group( 'tollbit_ai_bot' );
		\Automattic\VIP\Cache\Vary_Cache::set_group_for_user( 'tollbit_ai_bot', 'tollbit_ai_bot' );
	}
} 
