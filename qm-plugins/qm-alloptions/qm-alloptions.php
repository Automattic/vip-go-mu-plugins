<?php
/**
 * Plugin Name: Query Monitor: AllOptions
 * Description: Shows sizes of values in alloptions (autoloaded-options)
 * Version: 0.1
 * Author: trepmal
 */

add_action('plugins_loaded', function() {
	/**
	 * Register collector, only if Query Monitor is enabled.
	 */
	if ( class_exists( 'QM_Collectors' ) ) {
		include 'class-qm-collector-alloptions.php';

		QM_Collectors::add( new QM_Collector_AllOptions() );
	}

	/**
	 * Register output. The filter won't run if Query Monitor is not
	 * installed so we don't have to explicity check for it.
	 */
	add_filter( 'qm/outputter/html', function( array $output, QM_Collectors $collectors ) {
		include 'class-qm-output-alloptions.php';
		if ( $collector = QM_Collectors::get( 'alloptions' ) ) {
			$output['alloptions'] = new QM_Output_AllOptions( $collector );
		}
		return $output;
	}, 101, 2 );
});