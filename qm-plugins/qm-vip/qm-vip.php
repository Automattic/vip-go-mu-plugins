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

	require_once __DIR__ . '/class-qm-vip-collector.php';

	QM_Collectors::add( new QM_VIP_Collector() );
	add_filter( 'qm/outputter/html', 'register_qm_vip_output', 120, 2 );
}

function register_qm_vip_output( array $output, \QM_Collectors $collectors ) {
	$collector = \QM_Collectors::get( 'vip' );
	if ( $collector ) {
		require_once __DIR__ . '/class-qm-vip-output-html.php';

		$output['vip'] = new QM_VIP_Output( $collector );
	}
	return $output;
}
