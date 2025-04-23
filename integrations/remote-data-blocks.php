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
	 * Returns `true` if Remote Data Blocks is already available e.g. via customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		// Check for the existence of the plugin version constant defined in the main plugin file.
		return defined( 'REMOTE_DATA_BLOCKS__LOADED' );
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

			// Get all the entries in the path of WPVIP_MU_PLUGIN_DIR/vip-integrations/remote-data-blocks-<version>/
			// and check what versions are available.
			$versions = $this->get_versions();

			// if no versions are found, return early.
			if ( empty( $versions ) ) {
				$this->is_active = false;
				return;
			}

			// Load the latest version of the plugin.
			$latest_directory = array_key_first( $versions );
			$load_path        = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/remote-data-blocks.php';

			// This check isn't strictly necessary, but better safe than sorry.
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		});
	}

	/**
	 * Get the available versions of Remote Data Blocks in descending order.
	 *
	 * @return array<string, string> An associative array of available versions, where the key is the
	 *                               directory name and the value is the version number. The versions
	 *                               are sorted in descending order.
	 */
	public function get_versions() {
		$versions = [];
		$dir      = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/';
		if ( ! is_dir( $dir ) ) {
			return $versions;
		}

		$scan_entries = scandir( $dir );
		foreach ( $scan_entries as $entry ) {
			if (
				str_contains( $entry, 'remote-data-blocks-' ) &&
				is_dir( $dir . $entry ) &&
				file_exists( $dir . $entry . '/remote-data-blocks.php' )
			) {
				// Extract the version number from the directory name
				$versions[ $entry ] = str_replace( 'remote-data-blocks-', '', $entry );
			}
		}

		// Sort the versions in descending order.
		uksort( $versions, function ( $a, $b ) use ( $versions ) {
			return version_compare( $versions[ $b ], $versions[ $a ] );
		} );

		return $versions;
	}

	/**
	 * Configure Remote Data Blocks for VIP Platform.
	 *
	 * This is called after the integration is activated but before the plugin is loaded.
	 *
	 * @private
	 */
	public function configure(): void {
		if ( ! defined( 'REMOTE_DATA_BLOCKS_CONFIGS' ) ) {
			define( 'REMOTE_DATA_BLOCKS_CONFIGS', [] );
		}
	}
}
