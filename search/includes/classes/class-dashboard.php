<?php

namespace Automattic\VIP\Search;

class Dashboard {
	public function __construct() {
		// Ensure Search menu is available on a per-site basis, whether EP_IS_NETWORK is defined or not. 
		add_action( 'admin_menu', '\ElasticPress\Dashboard\action_admin_menu' );

		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_menu' ), 60 );
		add_action( 'admin_print_styles', array( __CLASS__, 'hide_unsupported_features' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'admin_footer_text' ), 9999 );
	}

	public static function admin_footer_text( $text ) {
		$screen = get_current_screen();
		if ( false !== strpos( $screen->base, 'elasticpress' ) || 'ep-pointer' === $screen->post_type ) {
			$text = '<p>Search on VIP is powered by <a href="https://10up.com/plugins/elasticpress/">10up\'s ElasticPress plugin</a>.</p>';
		}
		return $text;
	}

	/**
	 * Hide features that aren't supported from UI
	 */
	public static function hide_unsupported_features() {
		$screen = get_current_screen();
		if ( 'toplevel_page_elasticpress' === $screen->base ) {
			echo '<style>
			div.ep-feature.ep-feature-autosuggest, div.ep-feature.ep-feature-documents {
				display: none !important;
			}
			</style>';
		}
	}

	/**
	 * Remove certain submenu pages from Search menu.
	 */
	public static function admin_menu() {
		remove_submenu_page( 'elasticpress', 'elasticpress-settings' );
		remove_submenu_page( 'elasticpress', 'elasticpress-health' );
	}

	/**
	 * Remove ElasticPress network menu.
	 */
	public static function network_admin_menu() {
		remove_menu_page( 'elasticpress' );
	}

	/**
	 * Disable the Elasticpress Network Dashboard link
	 */
	public static function admin_bar_menu( $admin_bar ) {
		$admin_bar->remove_menu( 'network-admin-elasticpress' );
	}
}

new Dashboard();
