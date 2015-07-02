<?php
/*
 *
 *	Conierge depends entirely on Horizon
 *
 */

class Sailthru_Concierge {

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	function __construct() {

		// Register Concierge Javascripts

		// NOTE: HORIZON class is now handling this logic
		//add_action( 'wp_enqueue_scripts', array( $this, 'register_conceirge_scripts' ) );

	} // end constructor

	/**
	 * Registers and enqueues Horizon for every page, but only if setup has been completed.
	 * Once done, sets up the options for Conceirge
	 */

	public function register_conceirge_scripts( $hook ) {

		//$params = get_option('sailthru_concierge_options');

		//if( isset($params['sailthru_concierge_is_on']) && $params['sailthru_concierge_is_on'] ) {

			// Check first, otherwise js could throw errors
			//if( get_option('sailthru_setup_complete') ) {

				//wp_enqueue_script( 'sailthru-horizon', '//ak.sail-horizon.com/horizon/v1.js', array('jquery') );

			//}

		//}

	} // register_conceirge_scripts

} // end class
