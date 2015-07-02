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
	 */
	private $configurable_options = array (
		'STATIC_HOST'               => 'https://static.livepress.com',
		'LIVEPRESS_SERVICE_HOST'    => 'https://api.livepress.com',
		'OORTLE_VERSION'            => '1.5',
		'LIVEPRESS_CLUSTER'         => 'livepress.com',
		'LIVEPRESS_VERSION'         => '1.1.4',
		'TIMESTAMP_HTML_TEMPLATE'   => '<abbr title="###TIMESTAMP###" class="livepress-timestamp">###TIME###</abbr>',
		'TIMESTAMP_TEMPLATE'        => 'G:i',
		'AUTHOR_TEMPLATE'           => '<span class="livepress-update-author">###AUTHOR###</span>',
		'DEBUG'                     => FALSE,
		'SCRIPT_DEBUG'              => FALSE,
		'PLUGIN_SYMLINK'            => FALSE,
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
		if(!isset(self::$singleton_instance)) {
			self::$singleton_instance = new self;
		}
		return self::$singleton_instance;
	}

	/**
	 * Contructor that assigns the wordpress hooks, initialize the
	 * configurable options and gets the wordpress options set.
	 */
	private function __construct() {}

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
		return $this->configurable_options['SCRIPT_DEBUG'] || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG === true);
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
		$option_name = strtoupper($option_name);

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
	public function get_host_option($option_name) {
		return get_option($option_name);
	}
}
