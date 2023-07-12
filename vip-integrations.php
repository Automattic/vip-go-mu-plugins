<?php

/**
 * Plugin Name: VIP Integrations
 * Description: Plugin loading for VIP plugin integrations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Integrations;

defined( 'ABSPATH' ) || die();

// Safety during the initial rollout. Can be removed later.
if (
	! file_exists( __DIR__ . '/integrations/integration.php' )
	|| ! file_exists( __DIR__ . '/integrations/integrations.php' )
	|| ! file_exists( __DIR__ . '/integrations/block-data-api.php' )
) {
	return;
}

require_once __DIR__ . '/integrations/integration.php';
require_once __DIR__ . '/integrations/integrations.php';
require_once __DIR__ . '/integrations/block-data-api.php';

$vip_integrations = new Integrations();

// Register VIP integrations here
$vip_integrations->register( 'block-data-api', BlockDataApi::class );

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string $slug   A unique identifier for the integration.
 * @param array  $config An associative array of configuration values for the integration.
 */
function activate( string $integration_slug, array $config = [] ): void {
	global $vip_integrations;
	$vip_integrations->activate( $integration_slug, $config );
}

// Load integrations in muplugins_loaded:5 to allow integrations to hook
// muplugins_loaded:10 or any later action
add_action( 'muplugins_loaded', function() use ( $vip_integrations ) {
	$vip_integrations->load_active();
}, 5 );
