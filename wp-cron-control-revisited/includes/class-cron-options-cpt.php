<?php

namespace WP_Cron_Control_Revisited;

class Cron_Options_CPT {
	/**
	 * Class instance
	 */
	private static $__instance = null;

	public static function instance() {
		if ( ! is_a( self::$__instance, __CLASS__ ) ) {
			self::$__instance = new self;
		}

		return self::$__instance;
	}

	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Class properties
	 */

	private $post_type = 'wpccr_events';

	/**
	 * Register hooks
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register a private post type to store cron events
	 */
	public function register_post_type() {
		register_post_type( $this->post_type, array(
			'label'   => 'Cron Events',
			'public'  => false,
			'rewrite' => false,
			'export'  => false,
		) );
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */
}

Cron_Options_CPT::instance();
