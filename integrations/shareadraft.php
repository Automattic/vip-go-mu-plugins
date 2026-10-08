<?php
/**
 * Integration: Share a Draft.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Share a Draft integration.
 *
 * @private
 */
class ShareadraftIntegration extends Integration {
	public function is_loaded(): bool {
		return defined( 'VIP_SHAREADRAFT_LOADED' );
	}

	public function configure(): void {
		$configs = $this->get_env_config();

		// Network sites inherit the environment config, so a site-wide IP allowlist still applies to a
		// network site that has no config of its own.
		if ( is_multisite() ) {
			$configs = array_merge( $configs, $this->get_network_site_config() );
		}

		if ( ! defined( 'VIP_SHAREADRAFT_CONFIG' ) ) {
			define( 'VIP_SHAREADRAFT_CONFIG', $configs );
		}
	}

	/**
	 * Returns whether the current WordPress version meets the bundled plugin requirement.
	 */
	public function is_supported_wp_version( string $plugin_file ): bool {
		global $wp_version;

		$requirements = get_file_data( $plugin_file, [
			'wp' => 'Requires at least',
		] );

		return empty( $requirements['wp'] ) || version_compare( $wp_version, $requirements['wp'], '>=' );
	}

	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		add_action( 'plugins_loaded', function (): void {
			if ( $this->is_loaded() ) {
				return;
			}

			$latest_directory = $this->get_latest_version();
			if ( null === $latest_directory ) {
				$this->is_active = false;
				return;
			}

			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/shareadraft.php';
			if ( ! file_exists( $load_path ) || ! $this->is_supported_wp_version( $load_path ) ) {
				$this->is_active = false;
				return;
			}

			require_once $load_path;

			if ( ! $this->is_loaded() ) {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Get the latest bundled Share a Draft release.
	 *
	 * @return string|null The directory for the latest version, or null when unavailable.
	 */
	public function get_latest_version(): ?string {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'shareadraft', 'shareadraft.php' );
	}
}
