<?php
/**
 * Abstract plugin wrapper.
 *
 * @package query-monitor
 */

if ( ! class_exists( 'QM_Plugin' ) ) {
abstract class QM_Plugin {

	private $plugin = array();
	public static $minimum_php_version = '5.3.6';

	/**
	 * Class constructor
	 *
	 * @author John Blackbourn
	 **/
	protected function __construct( $file ) {
		$this->file = $file;
	}

	/**
	 * Returns the URL for for a file/dir within this plugin.
	 *
	 * @param $file string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string URL
	 * @author John Blackbourn
	 **/
	final public function plugin_url( $file = '' ) {
		return $this->_plugin( 'url', $file );
	}

	/**
	 * Returns the filesystem path for a file/dir within this plugin.
	 *
	 * @param $file string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Filesystem path
	 * @author John Blackbourn
	 **/
	final public function plugin_path( $file = '' ) {
		return $this->_plugin( 'path', $file );
	}

	/**
	 * Returns a version number for the given plugin file.
	 *
	 * @param $file string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Version
	 * @author John Blackbourn
	 **/
	final public function plugin_ver( $file ) {
		return filemtime( $this->plugin_path( $file ) );
	}

	/**
	 * Returns the current plugin's basename, eg. 'my_plugin/my_plugin.php'.
	 *
	 * @return string Basename
	 * @author John Blackbourn
	 **/
	final public function plugin_base() {
		return $this->_plugin( 'base' );
	}

	/**
	 * Populates and returns the current plugin info.
	 *
	 * @author John Blackbourn
	 **/
	final private function _plugin( $item, $file = '' ) {
		if ( ! array_key_exists( $item, $this->plugin ) ) {
			switch ( $item ) {
				case 'url':
					$this->plugin[ $item ] = plugin_dir_url( $this->file );
					break;
				case 'path':
					$this->plugin[ $item ] = plugin_dir_path( $this->file );
					break;
				case 'base':
					$this->plugin[ $item ] = plugin_basename( $this->file );
					break;
			}
		}
		return $this->plugin[ $item ] . ltrim( $file, '/' );
	}

	public static function php_version_met() {
		static $met = null;

		if ( null === $met ) {
			$met = version_compare( PHP_VERSION, self::$minimum_php_version, '>=' );
		}

		return $met;
	}

}
}
