<?php
/**
 * Plugin Name: Query Monitor: Object Cache
 * Description:
 * Version: 0.2
 * Author: trepmal, rebasaurus
 */

add_action( 'plugins_loaded', function() {
	/**
	 * Register collectors and css, only if Query Monitor is enabled.
	 */
	global $wp_object_cache;
	if ( class_exists( 'QM_Collectors' ) ) {
		add_action( 'wp_enqueue_scripts', 'qm_object_cache_assets' );
		add_action( 'admin_enqueue_scripts', 'qm_object_cache_assets' );

		if ( file_exists( __DIR__ . '/class-qm-data-object-cache.php' ) ) {
			require_once __DIR__ . '/class-qm-data-object-cache.php';
		}

		if ( file_exists( __DIR__ . '/collectors/class-qm-collector-object-cache.php' ) ) {
			require_once __DIR__ . '/collectors/class-qm-collector-object-cache.php';
			QM_Collectors::add( new QM_Collector_Object_Cache() );
		}

		if ( file_exists( __DIR__ . '/collectors/class-qm-collector-object-cache-ops.php' ) ) {
			require_once __DIR__ . '/collectors/class-qm-collector-object-cache-ops.php';
			QM_Collectors::add( new QM_Collector_Object_Cache_Ops() );
		}

		if ( file_exists( __DIR__ . '/collectors/class-qm-collector-object-cache-slow-ops.php' ) ) {
			require_once __DIR__ . '/collectors/class-qm-collector-object-cache-slow-ops.php';
			QM_Collectors::add( new QM_Collector_Object_Cache_Slow_Ops() );
		}
	}

	/**
	 * Register output. The filter won't run if Query Monitor is not
	 * installed so we don't have to explicity check for it.
	 */
	add_filter( 'qm/outputter/html', function( array $output, QM_Collectors $collectors ) {
		if ( file_exists( __DIR__ . '/html/class-qm-output-object-cache.php' ) ) {
			require_once __DIR__ . '/html/class-qm-output-object-cache.php';

			$collector = QM_Collectors::get( 'object_cache' );
			if ( $collector ) {
				$output['object_cache'] = new QM_Output_Html_Object_Cache( $collector );
			}
		}

		if ( file_exists( __DIR__ . '/html/class-qm-output-object-cache-ops.php' ) ) {
			require_once __DIR__ . '/html/class-qm-output-object-cache-ops.php';

			$collector = QM_Collectors::get( 'object_cache_ops' );
			if ( $collector ) {
				$output['object_cache_ops'] = new QM_Output_Html_Object_Cache_Ops( $collector );
			}
		}

		if ( file_exists( __DIR__ . '/html/class-qm-output-object-cache-slow-ops.php' ) ) {
			require_once __DIR__ . '/html/class-qm-output-object-cache-slow-ops.php';

			$collector = QM_Collectors::get( 'object_cache_slow_ops' );
			if ( $collector ) {
				$output['object_cache_slow_ops'] = new QM_Output_Html_Object_Cache_Slow_Ops( $collector );
			}
		}

		return $output;
	}, 101, 2 );
} );

function qm_object_cache_assets() {
	wp_enqueue_style( 'qm-object-cache-style', plugin_dir_url( __FILE__ ) . 'css/style.css', array(), '0.2' );
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
