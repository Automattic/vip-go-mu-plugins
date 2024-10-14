<?php
/**
 * Plugin Name: Query Monitor: VIP Concat
 * Description:
 * Version: 0.1
 * Author: trepmal
 */

add_action('plugins_loaded', function () {

	/**
	 * Register collector, only if Query Monitor is enabled.
	 */
	if ( class_exists( 'QM_Collectors' ) && class_exists( 'QM_Collector_Logger' ) ) {
		include_once 'class-qm-collector-vip-concat.php';

		QM_Collectors::add( new QM_Collector_VIPConcat() );
	}

	/**
	 * Register output. The filter won't run if Query Monitor is not
	 * installed so we don't have to explicity check for it.
	 */
	add_filter( 'qm/outputter/html', function ( array $output ) {
		include_once 'class-qm-output-vip-concat.php';
		$collector = QM_Collectors::get( 'vip_concat' );
		if ( $collector ) {
			$output['vip_concat'] = new QM_Output_VIPConcat( $collector );
		}
		return $output;
	}, 101 );
} );
