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

class VipIntegrations {
	public function setup() {
		add_action( 'muplugins_loaded', [ $this, 'register' ], /* priority */ 4 );
		add_action( 'muplugins_loaded', [ $this, 'activate' ], /* priority */ 6 );
		add_action( 'muplugins_loaded', [ $this, 'load' ], /* priority */ 8 );
	}

	// Actions

	public function register(): void {
		add_action( 'vip_integrations_register', function() {
			// Register VIP integrations here
			Integrations::instance()->register( 'block-data-api', BlockDataApi::class );
		} );

		do_action( 'vip_integrations_register' );
	}

	public function activate(): void {
		do_action( 'vip_integrations_activate' );
	}

	public function load(): void {
		add_action( 'vip_integrations_load', function() {
			Integrations::instance()->load_active();
		} );

		do_action( 'vip_integrations_load' );
	}
}

$vip_integrations = new VipIntegrations();
$vip_integrations->setup();

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string $slug   A unique identifier for the integration.
 * @param array  $config An associative array of configuration values for the integration.
 */
function activate( string $integration_slug, array $config = [] ) {
	add_action( 'vip_integrations_activate', function() use ( $integration_slug, $config ) {
		$integration = Integrations::instance()->get( $integration_slug );

		if ( null === $integration ) {
			throw new InvalidArgumentException( sprintf( 'VIP Integration with slug "%s" is not a registered integration.', $integration_slug ) );
		} else {
			$integration->activate( $config );
		}
	} );
}
