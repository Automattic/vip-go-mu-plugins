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
	/**
	 * The version of Share a Draft to load, defaults to the latest version.
	 *
	 * @var string
	 */
	public string $version = 'latest';

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

		if ( isset( $configs['version'] ) && is_string( $configs['version'] ) && '' !== $configs['version'] ) {
			$this->version = $configs['version'];
		}
	}

	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		add_action( 'plugins_loaded', function (): void {
			if ( $this->is_loaded() ) {
				return;
			}

			$versions = $this->get_versions();

			if ( empty( $versions ) ) {
				$this->is_active = false;
				return;
			}

			$selected_version_folder = $this->get_selected_version_folder( $versions );
			$load_path               = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $selected_version_folder . '/shareadraft.php';

			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		}, 1 );
	}

	/**
	 * Get the available versions of Share a Draft in descending order.
	 *
	 * @return array<string,string>
	 */
	public function get_versions(): array {
		return get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'shareadraft', 'shareadraft.php' );
	}

	/**
	 * Get the folder name for the selected version of the integration.
	 *
	 * @param array<string,string> $versions Available versions keyed by folder name.
	 * @return string The selected folder name.
	 */
	public function get_selected_version_folder( array $versions ): string {
		if ( 'latest' === $this->version ) {
			return array_key_first( $versions );
		}

		$desired_version = array_search( $this->version, $versions, true );

		if ( false !== $desired_version ) {
			return $desired_version;
		}

		return array_key_first( $versions );
	}
}
