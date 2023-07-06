<?php
/**
 * Plugin Name: VIP Integrations
 * Description: Plugin for loading integrations provided by VIP.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

defined( 'ABSPATH' ) || die();

require_once __DIR__ . '/integrations/integration.php';
require_once __DIR__ . '/integrations/integrations.php';
require_once __DIR__ . '/integrations/block-data-api.php';
require_once __DIR__ . '/integrations/parsely.php';

/**
 * List of integrations supported by VIP.
 *
 * If the integration is managed by VIP then slug should match with backend.
 *
 * @var array<Integration>
 */
$supported_vip_integrations = array(
	new BlockDataApiIntegration( 'block-data-api' ),
	new ParselyIntegration( 'parsely' ),
);

global $vip_integrations;
$vip_integrations = new Integrations();

// Register VIP integrations here.
foreach ( $supported_vip_integrations as $integration ) {
	$vip_integrations->register( $integration );
}

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string $slug A unique identifier for the integration.
 * @param array  $config An associative array of configuration values for the integration.
 */
function activate( string $slug, array $config = [] ): void {
	global $vip_integrations;
	$vip_integrations->activate( $slug, $config );
}

// Load integrations in muplugins_loaded:5 to allow integrations to hook
// muplugins_loaded:10 or any later action.
add_action( 'muplugins_loaded', function() {
	global $vip_integrations;
	$vip_integrations->load_active();
}, 5 );
