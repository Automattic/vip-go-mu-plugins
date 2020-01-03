<?php

namespace Automattic\VIP\Elasticsearch;

use \WP_CLI;

class Elasticsearch {

	/**
	 * Initialize the VIP Elasticsearch plugin
	 */
	public function init() {
		if ( defined( 'USE_VIP_ELASTICSEARCH' ) && USE_VIP_ELASTICSEARCH ) {
			$this->load_dependencies();
			$this->add_hooks();
			$this->load_commands();
		}
	}

	protected function load_dependencies() {
		/**
		 * Load ES Health command class
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once 'class-health-command.php';
		}
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
