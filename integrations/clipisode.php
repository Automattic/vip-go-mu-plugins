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
	/**
	 * Returns whether Clipisode is already available through customer code.
	 */
	public function is_loaded(): bool {
		return defined( 'CLIPISODE_VERSION' );
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

	/**
	 * Loads the latest bundled Clipisode release.
	 *
	 * @private
	 */
	public function load(): void {
		add_action( 'plugins_loaded', function (): void {
			if ( $this->is_loaded() ) {
				return;
			}

			$latest_directory = $this->get_latest_version();
			if ( null === $latest_directory ) {
				$this->is_active = false;
				return;
			}

			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/clipisode.php';
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
	 * Gets the latest bundled Clipisode release.
	 *
	 * @return string|null The directory for the latest version, or null when unavailable.
	 */
	public function get_latest_version(): ?string {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'clipisode', 'clipisode.php' );
	}
}
