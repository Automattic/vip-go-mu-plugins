<?php

namespace WP_Cron_Control_Revisited;

abstract class Singleton {
	/**
	 * Class instance
	 */
	private static $__instance = null;

	public static function instance() {
		$caller = get_called_class();

		if ( ! is_a( self::$__instance, $caller ) ) {
			self::$__instance = new $caller();

			self::$__instance->class_init();
		}

		return self::$__instance;
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
