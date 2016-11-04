<?php

namespace WP_Cron_Control_Revisited;

abstract class Singleton {
	/**
	 * Class instance
	 */
	private static $__instances = array();

	public static function instance() {
		$caller = get_called_class();

		if ( ! isset( self::$__instances[ $caller ] ) ) {
			self::$__instances[ $caller ] = new $caller();

			self::$__instances[ $caller ]->class_init();
		}

		return self::$__instances[ $caller ];
	}

	protected function __construct() {}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Register hooks
	 */
	protected function class_init() {}
}
