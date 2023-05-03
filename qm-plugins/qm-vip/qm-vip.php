<?php

/**
 * Plugin Name: Query Monitor VIP
 * Description: Additional collector for Query Monitor for VIP-specific information.
 * Version: 1.0
 * Author: Automattic, Rebecca Hum
 */

if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	return;
}

add_action( 'plugins_loaded', 'register_qm_vip' );
function register_qm_vip() {
	if ( ! class_exists( 'QM_Collectors' ) ) {
		return;
	}

	if ( file_exists( __DIR__ . '/class-qm-data-vip.php' ) ) {
		require_once __DIR__ . '/class-qm-data-vip.php';
	}
	if ( file_exists( __DIR__ . '/class-qm-collector-vip.php' ) ) {
		require_once __DIR__ . '/class-qm-collector-vip.php';
	}

	QM_Collectors::add( new QM_Collector_VIP() );
	add_filter( 'qm/outputter/html', 'register_qm_vip_output', 120, 2 );
}

function register_qm_vip_output( array $output, \QM_Collectors $collectors ) {
	$collector = \QM_Collectors::get( 'vip' );
	if ( $collector && file_exists( __DIR__ . '/class-qm-output-html-vip.php' ) ) {
		require_once __DIR__ . '/class-qm-output-html-vip.php';

		$output['vip'] = new QM_Output_Html_VIP( $collector );
	}
	return $output;
}
