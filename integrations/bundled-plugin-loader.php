<?php
/**
 * Loading shared by integrations that bundle a plugin release in vip-integrations/.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads the latest bundled release of a plugin, unless customer code already loaded it or the
 * current WordPress version does not meet the release's "Requires at least" header.
 *
 * @private
 */
trait LoadsLatestBundledPlugin {
	/**
	 * Gets the folder of the latest bundled release.
	 *
	 * @return string|null The directory for the latest version, or null when unavailable.
	 */
	abstract public function get_latest_version(): ?string;

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

	/**
	 * Loads the latest bundled release on plugins_loaded, so a copy in the customer repo takes precedence.
	 *
	 * @param string $entry_file The plugin's entry file in its release folder.
	 */
	private function load_latest_bundled_plugin( string $entry_file ): void {
		add_action( 'plugins_loaded', function () use ( $entry_file ): void {
			if ( $this->is_loaded() ) {
				return;
			}

			$latest_directory = $this->get_latest_version();
			if ( null === $latest_directory ) {
				$this->is_active = false;
				return;
			}

			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/' . $entry_file;
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
}
