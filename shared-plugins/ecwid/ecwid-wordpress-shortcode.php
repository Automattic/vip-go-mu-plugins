<?php
/*
Plugin Name: Ecwid Shopping Cart Shortcode
Plugin URI: http://www.ecwid.com/ 
Description: Ecwid is a free full-featured shopping cart. It can be easily integreted with any Wordpress blog and takes less than 5 minutes to set up.
Author: Ecwid Team
Version: 0.3 
Author URI: http://www.ecwid.com/
*/

if ( ! defined( 'ECWID_PLUGIN_DIR' ) ) {
	define( 'ECWID_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ECWID_PLUGIN_URL' ) ) {
	define( 'ECWID_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once ECWID_PLUGIN_DIR . "/class-ecwid-shopping-cart.php";

$ecwid = new Ecwid_Shopping_Cart();

?>
