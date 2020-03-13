<?php

namespace Automattic\VIP\Search;

class Dashboard {

	function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	/**
	 * Disable the Elasticpress dashboard
	 */
	static function admin_init() {
		if ( ! is_automattician() ) {
			remove_menu_page( 'elasticpress' );
		}
	}
}

new Dashboard;
