<?php

namespace Automattic\VIP\Elasticsearch;

use \WP_CLI;

class Elasticsearch {

	/**
	 * Initialize the VIP Elasticsearch plugin
	 */
	public function init() {
		$this->load_dependencies();
		$this->add_hooks();
		$this->load_commands();
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/class-health-command.php';
		}
		// Load ElasticPress
		require_once __DIR__ . '/elasticpress/elasticpress.php';
	}

	protected function add_hooks() {
		// Add filters and action hooks here
	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-es health', __NAMESPACE__ . '\Health_Command' );
		}
	}
}
