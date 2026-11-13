<?php
/**
 * Integration: Gist Answers.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// Deploys are non-atomic, so this file can arrive before the trait it uses. Without the trait, skip
// declaring the class, which leaves the integration unregistered until the deploy finishes.
if ( ! file_exists( __DIR__ . '/bundled-plugin-loader.php' ) ) {
	return;
}

require_once __DIR__ . '/bundled-plugin-loader.php';

/**
 * Loads the Gist Answers integration, a partner plugin from ProRata.
 *
 * VIP chooses when each Gist Answers release ships, so this loads one exact version and never the
 * latest folder present. Shipping a new release means adding its folder to vip-integrations/ and
 * bumping VERSION; rolling back means reverting that bump.
 *
 * @private
 */
class GistAnswersIntegration extends Integration {
	use LoadsLatestBundledPlugin;

	/**
	 * The Gist Answers release to load, from vip-integrations/gist-answers-<VERSION>/.
	 */
	const VERSION = '1.0.16';

	public function is_loaded(): bool {
		// Defined at the top of the plugin's entry file.
		return defined( 'GIST_ANSWERS_VERSION' );
	}

	public function configure(): void {
		// Gist Answers is network-scoped, so every site in a network shares the environment config.
		if ( ! defined( 'VIP_GIST_ANSWERS_CONFIG' ) ) {
			define( 'VIP_GIST_ANSWERS_CONFIG', $this->get_env_config() );
		}
	}

	public function load(): void {
		$this->load_latest_bundled_plugin( 'gist-answers.php' );
	}

	/**
	 * Get the folder of the pinned release.
	 *
	 * Despite the name, which the shared loader requires, this returns only the pinned VERSION. It
	 * returns null when that folder is missing, so the integration fails closed instead of falling
	 * back to another release.
	 *
	 * @return string|null The pinned release's directory, or null when it is not bundled.
	 */
	public function get_latest_version(): ?string {
		$directory = 'gist-answers-' . static::VERSION;

		return is_dir( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $directory ) ? $directory : null;
	}
}
