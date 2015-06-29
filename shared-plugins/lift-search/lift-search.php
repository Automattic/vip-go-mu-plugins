<?php
/*
  Plugin Name: Lift Search
  Version: 1.9.1
  Plugin URI: http://getliftsearch.com/
  Description: Improves WordPress search using Amazon CloudSearch
  Author: Voce Platforms
  Author URI: http://voceconnect.com/
 */

if ( version_compare( phpversion(), '5.3.0', '>=') ) {
	require_once('lift-core.php');
}

function _lift_php_version_check() {
    if ( !class_exists( 'Lift_Search' ) ) {
        die( '<p style="font: 12px/1.4em sans-serif;"><strong>Lift Search requires PHP version 5.3 or higher. Installed version is: ' . phpversion() . '</strong></p>' );
    } elseif ( function_exists('_lift_activation') ) {
    	_lift_activation();
    }
}

register_activation_hook( __FILE__, '_lift_php_version_check' );
