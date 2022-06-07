<?php
/**
 * Plugin Name: Query Monitor: Object Cache
 * Description:
 * Version: 0.1
 * Author: trepmal
 */

add_action( 'plugins_loaded', function() {
	/**
	 * Register collector and css, only if Query Monitor is enabled.
	 */
	if ( class_exists( 'QM_Collectors' ) ) {
		add_action( 'wp_enqueue_scripts', 'qm_object_cache_assets' );
		add_action( 'admin_enqueue_scripts', 'qm_object_cache_assets' );

		include_once 'class-qm-collector-object-cache.php';

		QM_Collectors::add( new QM_Collector_ObjectCache() );
	}

	/**
	 * Register output. The filter won't run if Query Monitor is not
	 * installed so we don't have to explicity check for it.
	 */
	add_filter( 'qm/outputter/html', function( array $output, QM_Collectors $collectors ) {
		include_once 'class-qm-output-object-cache.php';
		$collector = QM_Collectors::get( 'object_cache' );
		if ( $collector ) {
			$output['object_cache'] = new QM_Output_ObjectCache( $collector );
		}
		return $output;
	}, 101, 2 );
} );

function qm_object_cache_assets() {
	wp_enqueue_style( 'qm-objectcache-style', plugin_dir_url( __FILE__ ) . 'css/style.css', array(), '0.1' );
}

/**
 * Cannot run Debug Bar's panel simultaneously,
 * as the output contains ID attributes.
 */
add_filter( 'debug_bar_panels', function( $panels ) {
	foreach ( $panels as $k => $panel ) {
		if ( is_a( $panel, 'Debug_Bar_Object_Cache' ) ) {
			unset( $panels[ $k ] );
		}
	}
	$panels = array_values( $panels ); // need to reset keys or Debug Bar throws warning
	return $panels;
} );
