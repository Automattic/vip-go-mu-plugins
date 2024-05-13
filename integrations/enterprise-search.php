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
		//
		// In activate() method we do make sure to not activate the integration if its already loaded
		// but still adding it here as a safety measure i.e. if load() is called directly.
		if ( $this->is_loaded() ) {
			return;
		}

		require_once __DIR__ . '/../search/search.php';
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
