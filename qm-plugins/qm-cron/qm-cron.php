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

	if ( file_exists( __DIR__ . '/class-qm-data-cron.php' ) ) {
		require_once __DIR__ . '/class-qm-data-cron.php';
	}
	if ( file_exists( __DIR__ . '/class-qm-collector-cron.php' ) ) {
		require_once __DIR__ . '/class-qm-collector-cron.php';
	}

	QM_Collectors::add( new QM_Collector_Cron() );
	add_filter( 'qm/outputter/html', 'register_qm_cron_output', 120, 2 );
}

function register_qm_cron_output( array $output, \QM_Collectors $collectors ) {
	$collector = \QM_Collectors::get( 'cron' );
	if ( $collector && file_exists( __DIR__ . '/class-qm-output-html-cron.php' ) ) {
		require_once __DIR__ . '/class-qm-output-html-cron.php';

		$output['cron'] = new QM_Output_Html_Cron( $collector );
	}
	return $output;
}
