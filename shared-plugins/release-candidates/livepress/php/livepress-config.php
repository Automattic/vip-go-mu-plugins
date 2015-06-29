<?php
/**
 * Livepress configuration
 *
 * @package Livepress
 */

/**
 * This file centralizes configuration options read from the config file or the host framework.
 */

class LivePress_Config {
	/**
	 * @access private
	 * @var array $configurable_options Configurable options.
	 * TODO: add fliters to template straings
	 */
	private $configurable_options = array (
		'STATIC_HOST'               => 'https://static.livepress.com',
		'LIVEPRESS_SERVICE_HOST'    => 'https://api.livepress.com',
		'OORTLE_VERSION'            => '1.5',
		'LIVEPRESS_CLUSTER'         => 'livepress.com',
		'LIVEPRESS_VERSION'         => '1.3',
		'TIMESTAMP_HTML_TEMPLATE'   => '<abbr title="###TIMESTAMP###" class="livepress-timestamp">###TIME###</abbr>',
		'TIMESTAMP_TEMPLATE'        => 'G:i',
		'AUTHOR_TEMPLATE'           => '<span class="livepress-update-author">###AUTHOR###</span>',
		'DEBUG'                     => false,
		'SCRIPT_DEBUG'              => false,
		'PLUGIN_SYMLINK'            => false,
		'LP_PLUGIN_NAME'            => 'livepress',
	);

	/**
	 * @static
	 * @access private
	 * @var null $singleton_instance
	 */
	private static $singleton_instance = null;

	/**
	 * Get instance.
	 *
	 * @static
	 *
	 * @return LivePress_Config|null
	 */
	public static function get_instance() {
		if ( ! isset(self::$singleton_instance) ) {
			self::$singleton_instance = new self;
		}
		return self::$singleton_instance;
	}

	/**
	 * Contructor that assigns the wordpress hooks, initialize the
	 * configurable options and gets the wordpress options set.
	 */
	private function __construct() {
		/**
		 * Filter Allows you to set the global author.
		 *
		 * @since 1.3
		 *
		 * @param string  $order_template a set of ###replace### target.
		 *
		 */
		$this->configurable_options['TIMESTAMP_HTML_TEMPLATE'] = apply_filters( 'livepress_global_time_template', $this->configurable_options['TIMESTAMP_HTML_TEMPLATE'] );
		/**
		 * Filter Allows you to change the order of the elements and add to teh meta info html.
		 *
		 * @since 1.3
		 *
		 * @param string  $order_template a set of ###replace### target.
		 *
		 */
		$this->configurable_options['AUTHOR_TEMPLATE'] = apply_filters( 'livepress_global_author_template', $this->configurable_options['AUTHOR_TEMPLATE'] );
	}

	/**
	 * Static host.
	 *
	 * @return mixed
	 */
	public function static_host() {
		return $this->configurable_options['STATIC_HOST'];
	}

	/**
	 * LivePress version.
	 *
	 * @return array
	 */
	public function lp_ver() {
		return array(
			$this->configurable_options['OORTLE_VERSION'],
			$this->configurable_options['LIVEPRESS_CLUSTER'],
			$this->configurable_options['LIVEPRESS_VERSION']
		);
	}

	/**
	 * LivePress service host.
	 *
	 * @return mixed
	 */
	public function livepress_service_host() {
		return $this->configurable_options['LIVEPRESS_SERVICE_HOST'];
	}

	/**
	 * LivePress debug.
	 *
	 * @return mixed
	 */
	public function debug() {
		return $this->configurable_options['DEBUG'];
	}

	/**
	 * LivePress script debug.
	 *
	 * @return bool
	 */
	public function script_debug() {
		return $this->configurable_options['SCRIPT_DEBUG'] || (defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true);
	}

	/**
	 * LivePress plugin symlink.
	 *
	 * @return mixed
	 */
	public function plugin_symlink() {
		return $this->configurable_options['PLUGIN_SYMLINK'];
	}

	/**
	 * LivePress option getter.
	 *
	 * @param string $option_name Option name.
	 * @return mixed
	 * @throws Exception
	 */
	public function get_option($option_name) {
		$option_name = strtoupper( $option_name );

		if ( ! isset( $this->configurable_options[ $option_name ] ) ) {
			_doing_it_wrong( 'LivePress_Config::get_option', 'Invalid livepress option.', 1 );
		}

		return $this->configurable_options[$option_name];
	}

	/**
	 * Get the option from the host framework (currently only WP).
	 *
	 * @param string $option_name
	 * @return mixed anything that can be saved
	 */
	public function get_host_option( $option_name ){
		return (false !== get_option( $option_name ) ) ? get_option( $option_name ) : false;
	}

	/**
	 * Gets a block of data to pass to the server about the current install.
	 *
	 * @param string $option_name
	 * @return mixed anything that can be saved
	 */
	public function get_debug_data(){
		$debug_data = array(
			'configurable_options'  => $this->configurable_options,
			'server'                => $this->get_server_details(),
			'wp_version'            => get_bloginfo( 'version' ),
			'installed_plugins'     => $this->get_installed_plugins(),
			'installed_themes'      => $this->get_installed_themes(),
		);

		return $debug_data;
	}

	/**
	 * return an array for server settings
	 * @return array
	 */
	private function get_server_details(){
		$server_report = array();
		$server_report['hostname'] = gethostname();
		$server_report['ip'] = gethostbyname( $server_report['hostname'] );
		$server_report['php_version'] = PHP_VERSION;
		return $server_report;
	}

	/**
	 * Returns an array with the installed plugins
	 * @return array
	 */
	private function get_installed_plugins(){
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		// add the MU plugin details if any
		$plugins['mu_plugins'] = get_mu_plugins();
		return $plugins;
	}

	/**
	 * Returns an array with the installed themes
	 * @return array
	 */
	private function get_installed_themes(){
		$installed_themes = wp_get_themes( array( 'errors' => false , 'allowed' => null ) );
		$themes = array();
		foreach ( $installed_themes as $key => $theme ){
			//          var_dump($theme);
			$themes[$key] = array(
				'Name'    => $theme->name,
				'Version' => $theme->version
			);
		}
		return $themes;
	}

} // end of class LivePress_Config

