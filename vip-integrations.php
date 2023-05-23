<?php

/**
 * Plugin Name: VIP Integrations
 * Description: Plugin loading for VIP plugin integrations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Integrations;

use InvalidArgumentException;

defined( 'ABSPATH' ) || die();

require_once __DIR__ . '/vip-integration-helper/integrations/integration.php';
require_once __DIR__ . '/vip-integration-helper/integrations/integrations.php';
require_once __DIR__ . '/vip-integration-helper/integrations/block-data-api.php';

// Register integrations

add_action( 'muplugins_loaded', __NAMESPACE__ . '\\run_register_action', /* priority */ 4 );
/**
 * Run action to register integrations.
 */
function run_register_action(): void {
	do_action( 'register_vip_integrations' );
}

add_action( 'register_vip_integrations', __NAMESPACE__ . '\\register_vip_integrations' );
/**
 * Register valid integrations.
 */
function register_vip_integrations(): void {
	Integrations::instance()->register( 'block-data-api', BlockDataApi::class );
}

// Activate integrations

add_action( 'muplugins_loaded', __NAMESPACE__ . '\\run_activate_action', /* priority */ 6 );
/**
 * Run action to activate integrations.
 */
function run_activate_action(): void {
	do_action( 'activate_vip_integrations' );
}

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string $slug   A unique identifier for the integration.
 * @param array  $config An associative array of configuration values for the integration.
 */
function activate( string $integration_slug, array $config = [] ) {
	add_action( 'activate_vip_integrations', function() use ( $integration_slug, $config ) {
		$integration = Integrations::instance()->get_registered( $integration_slug );

		if ( null === $integration ) {
			throw new InvalidArgumentException( sprintf( 'VIP Integration with slug "%s" is not a registered integration.', $integration_slug ) );
		} else {
			$integration->activate( $config );
		}
	}, /* priority */ 6 );
}

// Run integration code

add_action( 'muplugins_loaded', __NAMESPACE__ . '\\run_integrate_action', /* priority */ 8 );
/**
 * Run integration action.
 */
function run_integrate_action(): void {
	do_action( 'integrate_vip_integrations' );
}

add_action( 'integrate_vip_integrations', __NAMESPACE__ . '\\integrate_vip_integrations' );
/**
 * After code has the opportunity to activate integrations, integrate them.
 */
function integrate_vip_integrations(): void {
	Integrations::instance()->integrate();
}
