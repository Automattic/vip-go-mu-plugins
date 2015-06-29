<?php
/*
  Plugin Name: Objects to Objects
  Version:     1.2.4
  Plugin URI:  http://voceplatforms.com
  Description: A WordPress plugin/module that provides the ability to map relationships between posts and other post types.
  Author:      Voce Platforms
  Author URI:  http://voceplatforms.com/
 */

if ( !class_exists( 'O2O' ) ) {
	require_once ( __DIR__ . '/src/o2o.php' );
}

if ( !class_exists( 'O2O_Connection_Factory' ) ) {
	require_once ( __DIR__ . '/src/factory.php' );
}

if ( !class_exists( 'O2O_Query' ) ) {
	require_once ( __DIR__ . '/src/query.php' );
}

if ( !class_exists( 'O2O_Rewrites' ) ) {
	require_once ( __DIR__ . '/src/rewrites.php' );
}

if ( !class_exists( 'O2O_Connection_Taxonomy' ) ) {
	require_once ( __DIR__ . '/src/connection-types/taxonomy/taxonomy.php' );
}

add_action( 'init', array( O2O::GetInstance(), 'init' ), 20 );