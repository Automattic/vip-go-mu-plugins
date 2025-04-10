<?php
/**
 * Abstract singleton for plugin's main classes
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * Singleton Class
 */
abstract class Singleton {
	/**
	 * Class instance
	 *
	 * @var array
	 */
	private static $__instances = array();

	/**
	 * Instantiate the class
	 *
	 * @return self
	 */
	public static function instance() {
		$caller = get_called_class();

		if ( ! isset( self::$__instances[ $caller ] ) ) {
			self::$__instances[ $caller ] = new $caller();

			self::$__instances[ $caller ]->class_init();
		}

		return self::$__instances[ $caller ];
	}

	/**
	 * Singleton constructor
	 */
	protected function __construct() {}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Register hooks
	 */
	protected function class_init() {}
}
