<?php

namespace Automattic\VIP\Config;

class Site_Details_Index {
	private static $_instance;

	private $timestamp = null;

	/**
	 * Standard singleton except accept a timestamp for mocking purposes.
	 *
	 * @param mixed $timestamp A fixed point in time to use for mocking.
	 * @return Site_Details_Index A Site_Details_Index object.
	 */
	public static function instance( $timestamp = null ) {
		if ( ! ( static::$_instance instanceof Site_Details_Index ) ) {
			static::$_instance = new Site_Details_Index();
			static::$_instance->set_current_timestamp( $timestamp );
			static::$_instance->init();
		}

		return static::$_instance;
	}

	public function init() {
		add_filter( 'vip_site_details_index_data', [ $this, 'set_env_and_core' ] );
	}

	/**
	 * Given the site details from the vip_site_details_index_data filter, set the environment and core values.
	 *
	 * @param array $site_details The current site details.
	 * @return array The new site details.
	 */
	public function set_env_and_core( $site_details ) {
		if ( ! is_array( $site_details ) ) {
			$site_details = array();
		}
		
		global $wp_version;

		$site_id = 0;
		if ( defined( 'VIP_GO_APP_ID' ) && VIP_GO_APP_ID ) {
			$site_id = VIP_GO_APP_ID;
		} else {
			if ( defined( 'FILES_CLIENT_SITE_ID' ) && FILES_CLIENT_SITE_ID ) {
				$site_id = FILES_CLIENT_SITE_ID;
			}
		}

		$environment_name = '';
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && VIP_GO_APP_ENVIRONMENT ) {
			$environment_name = strval( VIP_GO_APP_ENVIRONMENT );
		} else {
			if ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) {
				$environment_name = strval( VIP_GO_ENV );
			}
		}

		// log2logstash has it's own timestamp field as well. However, if/when we ever decide
		// to swap indexes, we'll need our own. Added here so that the migration will be easier
		// and all the fields we need are included in the actual site details index data.
		$site_details['timestamp'] = $this->get_current_timestamp();
		$site_details['client_site_id'] = $site_id;
		$site_details['environment_id'] = $site_id;
		$site_details['environment_name'] = $environment_name;
		$site_details['plugins'] = $this->get_plugin_info();
		$site_details['core']['wp_version'] = strval( $wp_version );
		$site_details['core']['blog_id'] = get_current_blog_id();
		$site_details['core']['is_multisite'] = is_multisite();
		
		return $site_details;
	}

	/**
	 * Gather up all the information about all the plugins.
	 *
	 * Does not contain anything from vip-go-mu-plugins.
	 */
	public function get_plugin_info() {
		// Needed or get_plugins can't be found in some instances
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$all_plugins = get_plugins();
		$active_plugins = get_option( 'active_plugins' );
		$plugins_enabled_via_code = wpcom_vip_get_filtered_loaded_plugins();

		$plugin_info = array();

		foreach ( $all_plugins as $key => $value ) {
			$active = in_array( $key, $active_plugins, true );
			$enabled_via_code = in_array( $key, $plugins_enabled_via_code, true );

			$plugin_info[] = array(
				'name' => $value['Name'],
				'version' => $value['Version'],
				'active' => $active,
				'enabled_via_code' => $enabled_via_code,
			);
		}

		return $plugin_info;
	}

	/**
	 * Get the site details for the site the code is running on.
	 */
	public function get_site_details() {
		/**
		 * Filter that can be hooked on to provide information to the Site Details Index.
		 *
		 * Example:
		 *
		 * array(
		 *     'client_site_id' => 4044,
		 *     'environment_id' => 4044,
		 *     'environment_name' => 'Testing Site',
		 *     'plugins' => array(
		 *         'WP-API' => '2.0-beta15',
		 *     ),
		 *     'core' => array(
		 *         'wp_version' => '5.5.1',
		 *         'blog_id' => 1,
		 *         'is_multisite' => false,
		 *     ),
		 *     'search' => array(
		 *         'enabled' => true,
		 *         'integration_enabled' => false,
		 *     ),
		 * )
		 *
		 * @hook vip_site_details_index_data
		 * @param {array} $site_details Default value for site details.
		 * @return {array} A nested array of site details.
		 */
		$site_details = apply_filters( 'vip_site_details_index_data', array() );

		return $site_details;
	}

	/**
	 * Returns the current value of $this->timestamp or time() if null.
	 *
	 * Used for mocking in tests.
	 *
	 * @return int The current timestamp or the value of $this->timestamp.
	 */
	public function get_current_timestamp() {
		return $this->timestamp ?? time();
	}

	/**
	 * Given a value, set the current timestamp to provided value if it's an integer.
	 *
	 * Used for mocking in tests.
	 */
	public function set_current_timestamp( $timestamp ) {
		if ( ! is_int( $timestamp ) ) {
			return;
		}

		$this->timestamp = $timestamp;
	}
}
