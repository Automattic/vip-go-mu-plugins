<?php

/**
 * Integration: Remote Data Blocks.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Remote Data Blocks VIP Integration.
 *
 * @private
 */
class RemoteDataBlocksIntegration extends Integration {
	/**
	 * Enable Pendo tracking for this integration.
	 *
	 * @var bool
	 */
	protected bool $enable_pendo_tracking = true;

	/**
	 * Returns `true` if Remote Data Blocks is already available e.g. via customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		// Check for the existence of the plugin version constant defined in the main plugin file.
		return defined( 'REMOTE_DATA_BLOCKS__LOADED' );
	}

	/**
	 * Returns `true` if the current WordPress version is supported by Remote Data Blocks.
	 *
	 * @return bool `true` if the current WordPress version greater than or equal to the minimum
	 *              WordPress version defined in the environment config, `false` otherwise.
	 */
	public function is_supported_wp_version(): bool {
		$wp_version = get_bloginfo( 'version' );

		$config = $this->get_env_config();
		if (
			isset( $config['minimum_wp_version'] ) &&
			is_string( $config['minimum_wp_version'] ) &&
			version_compare( $wp_version, $config['minimum_wp_version'], '>=' )
		) {
			return true;
		}

		/**
		 * Default to false if the minimum WordPress version is not set to avoid fatals due to
		 * Remote Data Blocks being loaded on unsupported WordPress versions.
		 */
		return false;
	}

	/**
	 * Loads the plugin.
	 *
	 * This is called after the integration is activated and configured.
	 *
	 * @private
	 */
	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		add_action('plugins_loaded', function () {
			// Return if the integration is already loaded.
			//
			// In activate() method we do make sure to not activate the integration if its already loaded
			// but still adding it here as a safety measure i.e. if load() is called directly.
			if ( $this->is_loaded() ) {
				return;
			}

			if ( ! $this->is_supported_wp_version() ) {
				$this->is_active = false;
				return;
			}

			// Load the latest version of the plugin.
			$latest_directory = $this->get_latest_version();

			if ( empty( $latest_directory ) ) {
				$this->is_active = false;
				return;
			}

			// Load the plugin.
			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/remote-data-blocks.php';

			// This check isn't strictly necessary, but better safe than sorry.
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		});
	}

	/**
	 * Get the latest version of Remote Data Blocks.
	 *
	 * @return string|null The latest version of Remote Data Blocks or null if no versions are found.
	 */
	public function get_latest_version() {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'remote-data-blocks', 'remote-data-blocks.php' );
	}

	/**
	 * Configure Remote Data Blocks for VIP Platform.
	 *
	 * This is called after the integration is activated but before the plugin is loaded.
	 *
	 * @private
	 */
	public function configure(): void {
		$combined_sources  = [];
		$child_env_configs = $this->get_child_env_configs();

		foreach ( $child_env_configs as $child_slug => $env_config ) {
			if (
				is_array( $env_config ) &&
				isset( $env_config['sources'] ) &&
				is_array( $env_config['sources'] ) &&
				! empty( $env_config['sources'] )
			) {
				// Add the service field to each source to identify which child integration it came from
				$sources_with_service = array_map( function ( $source ) use ( $child_slug ) {
					return array_merge( $source, [ 'service' => $child_slug ] );
				}, $env_config['sources'] );
				
				$combined_sources = array_merge( $combined_sources, $sources_with_service );
			}
		}

		if ( ! defined( 'REMOTE_DATA_BLOCKS_CONFIGS' ) ) {
			define( 'REMOTE_DATA_BLOCKS_CONFIGS', $combined_sources );
		}
	}
}
