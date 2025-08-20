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
	 * Loads the plugin.
	 */
	public function load(): void {
		// Return if the integration is already loaded.
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

		if ( ! defined( 'VIP_SEARCH_ENABLED_BY' ) ) {
			define( 'VIP_SEARCH_ENABLED_BY', 'integration' );
		}
	}

	/**
	 * Configure `Enterprise Search` for VIP Platform.
	 */
	public function configure(): void {
		if ( ! $this->is_es_credentials_set() ) {
			add_action( 'vip_search_loaded', array( $this, 'vip_set_es_credentials' ) );
		}
		add_action( 'vip_search_loaded', array( $this, 'vip_set_search_offloading' ) );
		add_action( 'vip_search_loaded', array( $this, 'vip_set_es_version' ) );
	}

	/**
	 * Set the Elasticsearch credentials.
	 */
	public function vip_set_es_credentials(): void {
		$config = $this->get_env_config();
		if ( ! isset( $config['username'] ) || ! isset( $config['password'] ) ) {
			return;
		}

		define( 'VIP_ELASTICSEARCH_USERNAME', $config['username'] );
		define( 'VIP_ELASTICSEARCH_PASSWORD', $config['password'] );
	}

	private function is_es_credentials_set(): bool {
		$username_defined = defined( 'VIP_ELASTICSEARCH_USERNAME' ) && constant( 'VIP_ELASTICSEARCH_USERNAME' );
		$password_defined = defined( 'VIP_ELASTICSEARCH_PASSWORD' ) && constant( 'VIP_ELASTICSEARCH_PASSWORD' );
		return $username_defined && $password_defined;
	}

	/**
	 * Set search offloading.
	 */
	public function vip_set_search_offloading(): void {
		$config = $this->get_env_config();
		if ( ! isset( $config['offload_search'] ) ) {
			return;
		}

		if ( 'true' === $config['offload_search'] ) {
			add_filter( 'vip_search_query_integration_enabled', '__return_true', PHP_INT_MAX );
		} elseif ( 'false' === $config['offload_search'] ) {
			add_filter( 'vip_search_query_integration_enabled', '__return_false', PHP_INT_MAX );
		}
	}

	/**
	 * Set the Elasticsearch version.
	 */
	public function vip_set_es_version(): void {
		$config = $this->get_env_config();

		$es_version = isset( $config['elasticsearch_version'] ) ? $config['elasticsearch_version'] : '7';
		define( 'VIP_ELASTICSEARCH_VERSION', $es_version );

		$migration_in_progress = isset( $config['elasticsearch_migration_in_progress'] ) ? $config['elasticsearch_migration_in_progress'] 
			: 'false';
		if ( 'true' === $migration_in_progress ) {
			define( 'VIP_ELASTICSEARCH_MIGRATION_IN_PROGRESS', true );
		}

		if ( '7' === $es_version && defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) && 'true' === $migration_in_progress ) {
			$original_hosts = constant( 'VIP_ELASTICSEARCH_ENDPOINTS' );
			if ( ! is_array( $original_hosts ) || empty( $original_hosts ) ) {
				return;
			}

			$migration_hosts = [];
			if ( isset( $original_hosts[0] ) ) {
				$migration_hosts[] = preg_replace( '/:\d+$/', ':9244', $original_hosts[0] );
			}
			if ( isset( $original_hosts[1] ) ) {
				$migration_hosts[] = preg_replace( '/:\d+$/', ':9245', $original_hosts[1] );
			}

			define( 'VIP_ELASTICSEARCH_MIGRATION_ENDPOINTS', $migration_hosts );
		}
	}
}
