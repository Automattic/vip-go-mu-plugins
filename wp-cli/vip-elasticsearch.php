<?php

namespace Automattic\VIP\CLI;

use \WP_CLI;
use \WP_CLI\Utils;
use \ElasticPress\Indexables as Indexables;

/**
 * Helper commands to manage VIP Go Elasticsearch indexes
 *
 * @package Automattic\VIP\CLI
 */
class VIP_Elasticsearch_CLI_Command extends \WPCOM_VIP_CLI_Command {

	/**
	 * @var WP_CLI\Fetchers\Plugin Plugin fetcher
	 */
	protected $plugin_fetcher;

	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		parent::__construct();

		$this->plugin_fetcher = new \WP_CLI\Fetchers\Plugin();
	}

	/**
	 * Validate DB and ES index post counts
	 *
	 * ## OPTIONS
	 *
	 *
	 * ## EXAMPLES
	 *     wp vip-es health validate-counts
	 *
	 * @subcommand validate-counts
	 */
	public function validate_counts( $args, $assoc_args ) {
		// Make sure ElasticPress is installed and active
		$this->is_elasticpress_active();

		// Get indexable objects
		$indexables = Indexables::factory()->get_all();

		foreach( $indexables as $indexable ) {
		}
	}

	protected function is_elasticpress_active() {
		$plugin = $this->plugin_fetcher->get( 'elasticpress' );

		if ( ! $plugin ) {
			WP_CLI::error( 'ElasticPress plugin not found' );
		}		

		$file = $plugin->file;

		if ( ! is_plugin_active( $file ) && ! is_plugin_active_for_network( $file ) ) {
			WP_CLI::error( 'ElasticPress plugin not active' );
		}
	}
}

WP_CLI::add_command( 'vip-es health', 'VIP_Elasticsearch_CLI_Command' );
