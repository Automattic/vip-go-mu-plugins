<?php

/**
 * If you're writing an addon, make sure you extend from this class.
 *
 * @since 1.1
 */
abstract class CampTix_Addon {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'camptix_init', array( $this, 'camptix_init' ) );
	}

	/**
	 * Initialization
	 */
	public function camptix_init() {}
}

/**
 * Register an addon
 *
 * @param string $class_name
 *
 * @return bool
 */
function camptix_register_addon( $class_name ) {
	/** @var $camptix CampTix_Plugin */
	global $camptix;

	return $camptix->register_addon( $class_name );
}
