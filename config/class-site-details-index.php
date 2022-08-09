<?php

namespace Automattic\VIP\Config;

class Site_Details_Index {
	/**
	 * Variable to hold the instance for the singleton.
	 *
	 * @var Site_Details_Index
	 */
	private static $instance;

	/**
	 * The timestamp that will be used to determine the value of Site_Details_Index::get_current_timestamp().
	 * Used for mocking. Integer values set the timestamp for the instance to that value. Null means it uses the current timestamp.
	 *
	 * @var int|null
	 */
	private $timestamp = null;

	/**
	 * Name of the logstash feature to use for log2logstash call
	 */
	private const LOG_FEATURE_NAME = 'site_details';

	/**
	 * Strings for Parsely Integration types
	 */
	const PARSELY_INTEGRATION_TYPE_MUPLUGINS_FILTER       = 'MUPLUGINS_FILTER';
	const PARSELY_INTEGRATION_TYPE_MUPLUGINS_OPTION       = 'MUPLUGINS_OPTION';
	const PARSELY_INTEGRATION_TYPE_MUPLUGINS_SELF_MANAGED = 'SELF_MANAGED';
	const PARSELY_INTEGRATION_TYPE_MUPLUGINS_NONE         = 'NONE';

	/**
	 * Strings for Parsely service types
	 */
	const PARSELY_SERVICE_TYPE_PAID   = 'PAID';
	const PARSELY_SERVICE_TYPE_SILENT = 'SILENT';
	const PARSELY_SERVICE_TYPE_NONE   = 'NONE';

	/**
	 * Strings for Parsely service enabled/disabled types
	 */
	const PARSELY_SERVICE_ENABLED_TYPE_FILTER  = 'FILTER';
	const PARSELY_SERVICE_ENABLED_TYPE_PLUGIN  = 'PLUGIN';
	const PARSELY_SERVICE_ENABLED_TYPE_FORM    = 'FORM';
	const PARSELY_SERVICE_DISABLED_TYPE_FILTER = 'FILTER';
	const PARSELY_SERVICE_DISABLED_TYPE_PLUGIN = 'PLUGIN';
	const PARSELY_SERVICE_DISABLED_TYPE_FORM   = 'FORM';

	/**
	 * Standard singleton except accept a timestamp for mocking purposes.
	 *
	 * @param mixed $timestamp A fixed point in time to use for mocking.
	 * @return Site_Details_Index A Site_Details_Index object.
	 */
	public static function instance( $timestamp = null ) {
		if ( ! ( static::$instance instanceof Site_Details_Index ) ) {
			static::$instance = new Site_Details_Index();
			static::$instance->set_current_timestamp( $timestamp );
			static::$instance->init();
		}

		return static::$instance;
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
		if ( defined( 'FILES_CLIENT_SITE_ID' ) && FILES_CLIENT_SITE_ID ) {
			$site_id = FILES_CLIENT_SITE_ID;
		}

		$environment_name = '';
		if ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && VIP_GO_APP_ENVIRONMENT ) {
			$environment_name = strval( VIP_GO_APP_ENVIRONMENT );
		}

		$site_details['timestamp']            = $this->get_current_timestamp();
		$site_details['client_site_id']       = $site_id;
		$site_details['environment_name']     = $environment_name;
		$site_details['core']['wp_version']   = strval( $wp_version );
		$site_details['core']['php_version']  = PHP_VERSION;
		$site_details['core']['blog_id']      = get_current_blog_id();
		$site_details['core']['site_url']     = get_site_url();
		$site_details['core']['home_url']     = get_home_url();
		$site_details['core']['is_multisite'] = is_multisite();

		$site_details['plugins'] = $this->get_plugin_info();
		$site_details['search']  = $this->get_search_info();
		$site_details['jetpack'] = $this->get_jetpack_info();
		$site_details['parsely'] = $this->get_parsely_info();

		return $site_details;
	}

	/**
	 * Gather up all the information about all the plugins.
	 *
	 * Does not contain anything from vip-go-mu-plugins.
	 */
	public function get_plugin_info() {
		// Ensure get_plugins() & wp_update_plugins() are available.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$all_plugins                = get_plugins();
		$update_data                = $this->get_plugin_update_data();
		$active_plugins             = get_option( 'active_plugins' );
		$network_plugins            = get_site_option( 'active_sitewide_plugins' );
		$plugins_activated_via_code = wpcom_vip_get_filtered_loaded_plugins();

		$plugin_info = array();
		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$activated_by = null;
			if ( in_array( $plugin_path, $plugins_activated_via_code, true ) ) {
				$activated_by = 'code';
			} elseif ( isset( $network_plugins[ $plugin_path ] ) ) {
				$activated_by = 'network';
			} elseif ( in_array( $plugin_path, $active_plugins, true ) ) {
				$activated_by = 'option';
			}

			$plugin_info[] = array(
				'path'          => $plugin_path,
				'name'          => $plugin_data['Name'],
				'version'       => $plugin_data['Version'],
				'active'        => null !== $activated_by,
				'activated_by'  => $activated_by,
				'wporg_slug'    => isset( $update_data[ $plugin_path ] ) ? $update_data[ $plugin_path ]['slug'] : null, // legacy, can be later removed.
				'slug'          => isset( $update_data[ $plugin_path ] ) ? $update_data[ $plugin_path ]['slug'] : null,
				'marketplace'   => isset( $update_data[ $plugin_path ] ) ? $update_data[ $plugin_path ]['marketplace'] : null,
				'has_update'    => isset( $update_data[ $plugin_path ] ) ? $update_data[ $plugin_path ]['new_version'] : null,
				'download_link' => isset( $update_data[ $plugin_path ] ) ? $update_data[ $plugin_path ]['package'] : null,
			);
		}

		return $plugin_info;
	}

	/**
	 * Get plugin information related to updates, such as their proper slug / marketplace / and download package url.
	 *
	 * Offical WP.org plugin slugs require a pretty technical process to properly find.
	 * WP core does this by querying a WPorg endpoint that checks for a valid plugin
	 * based on various factors like name, author, path, and plugin header values.
	 * So here we just piggyback off of the above process to locate the proper slugs.
	 *
	 * Third party plugins also tend to hook into this process and insert their own slugs / urls.
	 */
	private function get_plugin_update_data() {
		$update_data = [];

		// Ensure the update cache is fresh.
		wp_update_plugins();
		$update_cache = get_site_transient( 'update_plugins' );

		// Note that these lists only contain plugins that have been matched with a "marketplace", usually WPorg.
		$no_update  = isset( $update_cache->no_update ) && is_array( $update_cache->no_update ) ? $update_cache->no_update : [];
		$has_update = isset( $update_cache->response ) && is_array( $update_cache->response ) ? $update_cache->response : [];

		foreach ( $no_update as $plugin_path => $plugin_data ) {
			$update_data[ $plugin_path ] = [
				'slug'        => $plugin_data->slug ?? null,
				'marketplace' => $this->get_plugin_marketplace( $plugin_data->url ),
				'new_version' => null,
				'package'     => null,
			];
		}

		foreach ( $has_update as $plugin_path => $plugin_data ) {
			$update_data[ $plugin_path ] = [
				'slug'        => $plugin_data->slug ?? null,
				'marketplace' => $this->get_plugin_marketplace( $plugin_data->url ),
				'new_version' => $plugin_data->new_version ?? null,
				'package'     => wp_http_validate_url( $plugin_data->package ) !== false ? $plugin_data->package : null,
			];
		}

		return $update_data;
	}

	/**
	 * There is no "official" marketplace slug system. To help with our searches, we'll try to standardize them here.
	 */
	private function get_plugin_marketplace( $plugin_url ) {
		if ( false !== strpos( $plugin_url, '//wordpress.org' ) ) {
			return 'wp-org';
		}

		if ( false !== strpos( $plugin_url, '//woocommerce.com' ) ) {
			return 'woocommerce-com';
		}

		if ( false !== strpos( $plugin_url, '//www.advancedcustomfields.com' ) ) {
			return 'advancedcustomfields';
		}

		return null;
	}

	/**
	 * Gather basic information about VIP Search for a site
	 */
	public function get_search_info() {
		$search_info = array();

		if ( class_exists( '\Automattic\VIP\Search\Search' ) ) {
			$search_info['enabled']                   = true;
			$search_info['query_integration_enabled'] = \Automattic\VIP\Search\Search::is_query_integration_enabled();
		} else {
			$search_info['enabled']                   = false;
			$search_info['query_integration_enabled'] = false;
		}

		return $search_info;
	}

	/**
	 * Gather all the information about Jetpack
	 */
	public function get_jetpack_info() {
		$jetpack_info = array();

		if ( class_exists( 'Jetpack' ) ) {
			$jetpack_info['available'] = true;
			$jetpack_info['active']    = \Jetpack::is_active();
			$jetpack_info['id']        = \Jetpack::get_option( 'id' );
			$jetpack_info['version']   = JETPACK__VERSION;
			if ( defined( 'VIP_JETPACK_LOADED_VERSION' ) ) {
				$jetpack_info['vip_loaded_version'] = VIP_JETPACK_LOADED_VERSION;
			}
			$jetpack_info['active_modules']         = \Jetpack::get_active_modules();
			$jetpack_info['instant_search_enabled'] = class_exists( 'Jetpack_Search_Options' ) ? \Jetpack_Search_Options::is_instant_enabled() : true === (bool) get_option( 'instant_search_enabled' );
		} else {
			$jetpack_info['available'] = false;
		}

		return $jetpack_info;
	}



	/**
	 * Gather all the information about Parsely
	 */
	public function get_parsely_info() {
		$parsely_info = [];

		$parsely_info['active']               = \Parsely::is_active();
		$parsely_info['integration_type']     = get_parsely_integration_type(); // "MUPLUGINS_FILTER", "MUPLUGINS_OPTION, "SELF_MANAGED", "NONE" ( How parse.ly is enabled )
		$parsely_info['parsely_service_type'] = get_parsely_service_type(); // "NONE", "SILENT", "PAID" ( How the service is )
		$parsely_info['parsely_date_enabled'] = get_parsely_date_enabled();
		$parsely_info['date_disabled']        = get_parsely_date_disabled();
		$parsely_info['disable_type']         = get_parsely_disabled_type(); // Alex will send list (filter, plugin form, etc..)
		$parsely_info['enabled_type']         = get_parsely_enabled_type(); // Alex will send list
		$parsely_info['version']              = PARSELY__VERSION;

		return $parsely_info;
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
	 * Builds the site details structure and then puts it into logstash
	 * and sends it to the site details service
	 */
	public function put_site_details() {
		$site_details = $this->get_site_details();

		if ( defined( 'SERVICES_API_URL' ) && defined( 'SERVICES_AUTH_TOKEN' ) && ! empty( SERVICES_AUTH_TOKEN ) ) {
			$url = rtrim( SERVICES_API_URL, '/' ) . '/site-details/sites';

			$args = array(
				'method'  => 'PUT',
				'body'    => wp_json_encode( $site_details ),
				'headers' => array(
					'Authorization' => 'Bearer ' . SERVICES_AUTH_TOKEN,
					'Content-Type'  => 'application/json',
				),
			);

			vip_safe_wp_remote_request( $url, false, 3, 5, 10, $args );
		}
	}

	/**
	 * Returns the current value of $this->timestamp or microtime() if null.
	 *
	 * Used for mocking in tests.
	 *
	 * @return int The current timestamp or the value of $this->timestamp.
	 */
	public function get_current_timestamp() {
		$get_microtime_as_float    = true;
		$timestamp_in_milliseconds = round( microtime( $get_microtime_as_float ) * 1000 );
		return $this->timestamp ?? $timestamp_in_milliseconds;
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
