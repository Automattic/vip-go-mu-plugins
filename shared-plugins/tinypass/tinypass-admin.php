<?php

define( 'TINYPASS_FAVICON', 'http://www.tinypass.com/favicon.ico' );

tinypass_include();

require_once dirname( __FILE__ ) . '/tinypass-mode-settings.php';
require_once dirname( __FILE__ ) . '/tinypass-site-settings.php';
require_once dirname( __FILE__ ) . '/tinypass-form.php';
require_once dirname( __FILE__ ) . '/tinypass-install.php';
require_once dirname( __FILE__ ) . '/tinypass-page-options.php';

add_action( "admin_menu", 'tinypass_add_admin_pages' );

function tinypass_add_admin_pages() {
	add_menu_page( 'Tinypass', 'Tinypass', 'manage_options', 'tinypass.php', 'tinypass_mode_settings', TINYPASS_FAVICON );
	add_submenu_page( 'tinypass.php', 'Paywall', 'Paywall', 'manage_options', 'tinypass.php', 'tinypass_mode_settings' );
	add_submenu_page( 'tinypass.php', 'General', 'General', 'manage_options', 'TinyPassSiteSettings', 'tinypass_site_settings' );
}

/* Adding scripts to admin pages */
add_action( 'admin_enqueue_scripts', 'tinypass_add_admin_scripts' );

function tinypass_add_admin_scripts( $hook ) {
	if ( preg_match( '/TinyPass|tinypass/', $hook ) ) {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'suggest' );
		wp_enqueue_script( 'tinypass_admin', TINYPASS_PLUGIN_PATH . '/js/tinypass_admin.js', array(), false, false );
		wp_enqueue_style( 'tinypass.css', TINYPASS_PLUGIN_PATH . '/css/tinypass.css' );
	}
}