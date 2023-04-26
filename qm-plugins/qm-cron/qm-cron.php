<?php

/**
 * Plugin Name: Query Monitor Cron
 * Description: Additional collector for Query Monitor for Cron. Adapted from Debug Bar Cron.
 * Version: 1.0
 * Author: Automattic
 */

add_action( 'plugins_loaded', 'register_qm_cron' );
function register_qm_cron() {
	if ( ! class_exists( 'QM_Collectors' ) ) {
		return;
	}

	if ( file_exists( __DIR__ . '/class-qm-cron-data.php' ) ) {
		require_once __DIR__ . '/class-qm-cron-data.php';
	}
	require_once __DIR__ . '/class-qm-cron-collector.php';

	QM_Collectors::add( new QM_Collector_Cron() );
	add_filter( 'qm/outputter/html', 'register_qm_cron_output', 120, 2 );
}

function register_qm_cron_output( array $output, \QM_Collectors $collectors ) {
	$collector = \QM_Collectors::get( 'cron' );
	if ( $collector ) {
		require_once __DIR__ . '/class-qm-cron-output-html.php';

		$output['cron'] = new QM_Output_Html_Cron( $collector );
	}
	return $output;
}
