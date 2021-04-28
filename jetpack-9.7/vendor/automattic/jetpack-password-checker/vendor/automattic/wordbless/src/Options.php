<?php

namespace WorDBless;

use function dbless_default_options;

/**
 * Implements support to Options
 */
class Options {

	use Singleton, ClearCacheGroup;

	public $cache_group = 'options';

	/**
	 * Holds the stored options
	 *
	 * @var array
	 */
	public $options = array();

	private function __construct() {
		add_filter( 'alloptions', array( $this, 'get_all_options' ), 10 );
		add_filter( 'update_option', array( $this, 'update_option' ), 10, 3 );
		add_filter( 'add_option', array( $this, 'add_option' ), 10, 2 );
		add_filter( 'deleted_option', array( $this, 'delete_option' ) );

		add_filter( 'wordbless_wpdb_query_results', array( $this, 'filter_query' ), 10, 2 );
		$this->clear_cache_group();
	}

	/**
	 * Clear all stored options
	 *
	 * @return void
	 */
	public function clear_options() {
		$this->options = array();
	}

	/**
	 * Makes sure option is found when trying to delete it
	 *
	 * @param array  $query_results
	 * @param string $query
	 * @return array
	 */
	public function filter_query( $query_results, $query ) {
		global $wpdb;
		$pattern = '/^SELECT autoload FROM ' . preg_quote( $wpdb->options ) . ' WHERE option_name = \'([^ ]+)\'$/';
		if ( 1 === preg_match( $pattern, $query, $matches ) ) {
			if ( isset( $this->get_all_options()[ $matches[1] ] ) ) {
				return array(
					(object) array(
						'autoload' => 'no',
					),
				);
			}
		}
		return $query_results;
	}

	/**
	 * Gets the default options, always present
	 *
	 * @return array
	 */
	public function get_default_options() {
		return array(
			'site_url' => 'http://example.org',
			'home'     => 'http://example.org',
		);
	}

	/**
	 * Filters alloptions
	 *
	 * @param array $options
	 * @return array
	 */
	public function get_all_options( $options = array() ) {
		$defaults = $this->get_default_options();

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$custom_defaults = array();
		if ( function_exists( 'dbless_default_options' ) ) {
			$custom_defaults = dbless_default_options();
		}

		$all_options = array_merge(
			$this->options,
			$defaults,
			$options,
			$custom_defaults
		);
		$this->clear_cache_group();

		return $all_options;
	}

	public function add_option( $option, $value ) {
		$this->options[ $option ] = $value;
		$this->clear_cache_group();
	}

	public function update_option( $option, $old_value, $value ) {
		$this->options[ $option ] = $value;
		$this->clear_cache_group();
	}

	public function delete_option( $option ) {
		unset( $this->options[ $option ] );
		$this->clear_cache_group();
	}

}
