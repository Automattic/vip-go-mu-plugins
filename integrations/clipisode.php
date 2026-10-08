<?php
/**
 * Integration: Clipisode.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the Clipisode integration.
 *
 * @private
 */
class ClipisodeIntegration extends Integration {
	use LoadsLatestBundledPlugin;

	/**
	 * Returns whether Clipisode is already available through customer code.
	 */
	public function is_loaded(): bool {
		return defined( 'CLIPISODE_VERSION' );
	}

	/**
	 * Loads the latest bundled Clipisode release.
	 *
	 * @private
	 */
	public function load(): void {
		$this->load_latest_bundled_plugin( 'clipisode.php' );
	}

	/**
	 * Gets the latest bundled Clipisode release.
	 *
	 * @return string|null The directory for the latest version, or null when unavailable.
	 */
	public function get_latest_version(): ?string {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'clipisode', 'clipisode.php' );
	}
}
