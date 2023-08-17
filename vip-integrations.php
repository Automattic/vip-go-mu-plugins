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
require_once __DIR__ . '/integrations/enums.php';
require_once __DIR__ . '/integrations/integration-config.php';
require_once __DIR__ . '/integrations/block-data-api.php';
require_once __DIR__ . '/integrations/parsely.php';

// Register VIP integrations here.
IntegrationsSingleton::instance()->register( new BlockDataApiIntegration( 'block-data-api' ) );
IntegrationsSingleton::instance()->register( new ParselyIntegration( 'parsely' ) );

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string              $slug A unique identifier for the integration.
 * @param array<string,mixed> $config An associative array of configuration values for the integration.
 */
function activate( string $slug, array $config = [] ): void {
	IntegrationsSingleton::instance()->activate( $slug, $config );
}

// Load integrations in muplugins_loaded:5 to allow integrations to hook
// muplugins_loaded:10 or any later action.
add_action( 'muplugins_loaded', function() {
	IntegrationsSingleton::instance()->activate_platform_integrations();
	IntegrationsSingleton::instance()->load_active();
}, 5 );

/**
 * Singleton class for managing integrations.
 */
class IntegrationsSingleton {
	/**
	 * Instance for Integrations.
	 *
	 * @var Integrations|null
	 */
	private static $instance = null;

	/**
	 * Get Integrations instance (initialise if null)
	 *
	 * @return Integrations
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Integrations();
		}

		return self::$instance;
	}
}
