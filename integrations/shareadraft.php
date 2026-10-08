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
	use LoadsLatestBundledPlugin;

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

	public function load(): void {
		$this->load_latest_bundled_plugin( 'shareadraft.php' );
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
