<?php
/*
Plugin Name: Daylife
Description: Add high quality licensed images relevant to your blog posts from sources like Getty, AP, Reuters and US Presswire.
Version: 1.1
License: GPL
Author: Daylife, Pete Mall
Author URI: http://daylife.com/
Text Domain: daylife
Domain Path: /i18n
*/

class WP_Daylife {
	public static $instance;

	public function __construct() {
		self::$instance = $this;

		if ( is_admin() ) {
			require( dirname( __FILE__ ) . '/inc/options.php' );
			require( dirname( __FILE__ ) . '/inc/meta-box.php' );
		}
	}
}

new WP_Daylife;
