<?php
/**
 * Integration: Enterprise Search.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Enterprise Search VIP Integration.
 */
class EnterpriseSearchIntegration extends Integration {

	/**
	 * The version of Enterprise Search to load.
	 *
	 * @var string
	 */
	protected string $version = '1.0';

	/**
	 * Returns `true` if Enterprise Search is already available e.g. customer code. We will use
	 * this function to prevent loading of integration again from platform side.
	 */
	public function is_loaded(): bool {
		return class_exists( \Automattic\VIP\Search\Search::class );
	}

	/**
	 * Activates this integration with given options array.
	 *
	 * @param array $options An associative options array for the integration.
	 *                       This can contain common parameters and integration specific parameters in `config` key.
	 *
	 * @private
	 */
	public function activate( array $options = [] ): void {
		// If integration is already available in customer code then don't activate it from platform side.
		if ( $this->is_loaded() ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// Do nothing.
		}

		// Don't do anything if integration is already activated.
		if ( $this->is_active() ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// Do nothing.
		}

		$this->is_active = true;
		$this->options   = $options;
	}

	/**
	 * Loads the plugin.
	 */
	public function load(): void {
		// Return if the integration is already loaded.
		//
		// In activate() method we do make sure to not activate the integration if its already loaded
		// but still adding it here as a safety measure i.e. if load() is called directly.
		if ( $this->is_loaded() ) {
			return;
		}

		// Load the version of the plugin that should be set to the latest version, otherwise if it's not found, fallback to the one in MU.
		$load_path    = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/vip-enterprise-search-' . $this->version . '/src/search.php';
		$use_versions = false; // Remove this once we are ready to use the versioned plugin.
		if ( $use_versions && file_exists( $load_path ) ) {
			require_once $load_path;
		} else {
			require_once __DIR__ . '/../search/search.php';
		}
	}

	/**
	 * Configure `Enterprise Search` for VIP Platform.
	 */
	public function configure(): void {
		if ( $this->is_es_credentials_set() ) {
			return;
		}

		add_action( 'vip_search_loaded', array( $this, 'vip_set_es_credentials' ) );
	}

	/**
	 * Set the Elasticsearch credentials.
	 */
	public function vip_set_es_credentials(): void {
		$config = $this->get_config();
		if ( isset( $config['username'] ) && isset( $config['password'] ) ) {
			define( 'VIP_ELASTICSEARCH_USERNAME', $config['username'] );
			define( 'VIP_ELASTICSEARCH_PASSWORD', $config['password'] );
		}
	}

	private function is_es_credentials_set(): bool {
		$username_defined = defined( 'VIP_ELASTICSEARCH_USERNAME' ) && constant( 'VIP_ELASTICSEARCH_USERNAME' );
		$password_defined = defined( 'VIP_ELASTICSEARCH_PASSWORD' ) && constant( 'VIP_ELASTICSEARCH_PASSWORD' );
		return $username_defined && $password_defined;
	}
}
