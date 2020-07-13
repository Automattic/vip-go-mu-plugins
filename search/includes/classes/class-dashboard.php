<?php

namespace Automattic\VIP\Search;

class Dashboard {
	public function __construct() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
	}

	/**
	 * Disable the Elasticpress dashboard
	 */
	public static function admin_menu() {
		if ( ! is_automattician() ) {
			remove_menu_page( 'elasticpress' );
		}
	}
}

new Dashboard();
