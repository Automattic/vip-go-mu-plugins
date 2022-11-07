<?php

/**
 * Plugin Name: Query Monitor APCu Cache Collector
 * Description: Additional collector for Query Monitor for APCu Cache
 * Version: 1.0
 * Author: Automattic, Rebecca Hum
 */

add_action( 'plugins_loaded', 'register_qm_apcu_cache_collector' );
function register_qm_apcu_cache_collector() {
	if ( ! class_exists( 'QM_Collectors' ) ) {
		return;
	}

	require_once __DIR__ . '/class-qm-apcu-cache-collector.php';

	QM_Collectors::add( new QM_Apcu_Cache_Collector() );
	add_filter( 'qm/outputter/html', 'register_qm_apcu_cache_output', 120, 2 );
}

function register_qm_apcu_cache_output( array $output, \QM_Collectors $collectors ) {
	$collector = \QM_Collectors::get( 'qm-apcu-cache' );
	if ( $collector ) {
		require_once __DIR__ . '/class-qm-apcu-cache-output-html.php';

		$output['qm-apcu-cache'] = new QM_Apcu_Cache_Output( $collector );
	}
	return $output;
}
