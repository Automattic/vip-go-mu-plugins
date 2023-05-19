<?php

/**
 * Plugin Name: VIP Integrations
 * Description: Plugin loading for VIP plugin integrations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Integrations;

defined( 'ABSPATH' ) || die();

require_once __DIR__ . '/vip-integration-helper/integrations/integrations.php';
require_once __DIR__ . '/vip-integration-helper/integrations/block-data-api.php';

add_action( 'muplugins_loaded', __NAMESPACE__ . '\\register_integrations', /* priority */ 4 );

/**
 * Register valid integrations.
 */
function register_integrations(): void {
	Integrations::instance()->register( 'block-data-api', BlockDataApi::class );
}

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string $slug   A unique identifier for the integration.
 * @param array  $config An associative array of configuration values for the integration.
 */
function activate_integration( $integration_slug, $config = [] ) {
	add_action( 'muplugins_loaded', function() use ( $integration_slug, $config ) {
		$integration = Integrations::instance()->get_registered( $integration_slug );

		if ( null !== $integration ) {
			$integration->activate( $config );
		}
	}, /* priority */ 6 );
}

add_action( 'muplugins_loaded', __NAMESPACE__ . '\\integrate_integrations', /* priority */ 8 );
/**
 * After code has the opportunity to activate integrations, integrate them.
 */
function integrate_integrations(): void {
	Integrations::instance()->integrate();
}
