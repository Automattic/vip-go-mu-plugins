<?php

/**
 * Plugin Name: Query Monitor DB Connections
 * Description: Additional collector for Query Monitor for DB connection information.
 * Version: 1.0
 * Author: Automattic, Rebecca Hum
 */

if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	return;
}

add_action( 'plugins_loaded', 'register_qm_db_connections' );
function register_qm_db_connections() {
	if ( ! class_exists( 'QM_Collectors' ) ) {
		return;
	}

	if ( file_exists( __DIR__ . '/class-qm-data-db-connections.php' ) ) {
		require_once __DIR__ . '/class-qm-data-db-connections.php';
	}
	if ( file_exists( __DIR__ . '/class-qm-collector-db-connections.php' ) ) {
		require_once __DIR__ . '/class-qm-collector-db-connections.php';
	}

	QM_Collectors::add( new QM_Collector_DB_Connections() );
	add_filter( 'qm/outputter/html', 'register_qm_db_connections_output', 120 );
}

function register_qm_db_connections_output( array $output ) {
	$collector = \QM_Collectors::get( 'db-connections' );
	if ( $collector && file_exists( __DIR__ . '/class-qm-output-html-db-connections.php' ) ) {
		require_once __DIR__ . '/class-qm-output-html-db-connections.php';

		$output['qm-db-connections'] = new QM_Output_Html_DB_Connections( $collector );
	}
	return $output;
}
