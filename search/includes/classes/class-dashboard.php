<?php

namespace Automattic\VIP\Search;

class Dashboard {
	public function __construct() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_menu' ), 60 );
	}

	/**
	 * Disable the Elasticpress Dashboard links
	 */
	public static function admin_menu() {
		if ( ! is_automattician() ) {
			remove_menu_page( 'elasticpress' );
		}
	}

	/**
	 * Disable the Elasticpress Network Dashboard link
	 */
	public static function admin_bar_menu( $admin_bar ) {
		if ( ! is_automattician() ) {
			$admin_bar->remove_menu( 'network-admin-elasticpress' );
		}
	}
}

new Dashboard();
