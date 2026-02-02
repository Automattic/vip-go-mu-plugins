<?php
/**
 * Plugin Name: VIP Integrations
 * Description: Plugin for loading integrations provided by VIP.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package Automattic\VIP\Integrations
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

namespace Automattic\VIP\Integrations;

// @codeCoverageIgnoreStart -- the actual code here is tested individually in the unit tests.
defined( 'ABSPATH' ) || die();

require_once __DIR__ . '/integrations/integration.php';
require_once __DIR__ . '/integrations/integration-utils.php';
require_once __DIR__ . '/integrations/integrations.php';
require_once __DIR__ . '/integrations/enums.php';
require_once __DIR__ . '/integrations/integration-vip-config.php';
require_once __DIR__ . '/integrations/block-data-api.php';
require_once __DIR__ . '/integrations/parsely.php';
require_once __DIR__ . '/integrations/vip-governance.php';
require_once __DIR__ . '/integrations/enterprise-search.php';
require_once __DIR__ . '/integrations/security-boost.php';

if ( file_exists( __DIR__ . '/integrations/agentforce.php' ) ) {
	require_once __DIR__ . '/integrations/agentforce.php';
}

if ( file_exists( __DIR__ . '/integrations/remote-data-blocks.php' ) ) {
	require_once __DIR__ . '/integrations/remote-data-blocks.php';
}

if ( file_exists( __DIR__ . '/integrations/jetpack.php' ) ) {
	require_once __DIR__ . '/integrations/jetpack.php';
}

if ( file_exists( __DIR__ . '/integrations/real-time-collaboration.php' ) ) {
	require_once __DIR__ . '/integrations/real-time-collaboration.php';
}

// Register VIP integrations here.
IntegrationsSingleton::instance()->register( new BlockDataApiIntegration( 'block-data-api' ) );
IntegrationsSingleton::instance()->register( new ParselyIntegration( 'parsely' ) );
IntegrationsSingleton::instance()->register( new VipGovernanceIntegration( 'vip-governance' ) );
IntegrationsSingleton::instance()->register( new EnterpriseSearchIntegration( 'enterprise-search' ) );
IntegrationsSingleton::instance()->register( new SecurityBoostIntegration( 'security-boost' ) );

if ( class_exists( __NAMESPACE__ . '\\AgentforceIntegration' ) ) {
	IntegrationsSingleton::instance()->register( new AgentforceIntegration( 'agentforce' ) );
}

if ( class_exists( __NAMESPACE__ . '\\RemoteDataBlocksIntegration' ) ) {
	IntegrationsSingleton::instance()->register( new RemoteDataBlocksIntegration( 'remote-data-blocks' ) );
}

if ( class_exists( __NAMESPACE__ . '\\JetpackIntegration' ) ) {
	IntegrationsSingleton::instance()->register( new JetpackIntegration( 'jetpack' ) );
}

if ( class_exists( __NAMESPACE__ . '\\RealTimeCollaborationIntegration' ) ) {
	IntegrationsSingleton::instance()->register( new RealTimeCollaborationIntegration( 'real-time-collaboration' ) );
}

// @codeCoverageIgnoreEnd

/**
 * Activates an integration with an optional configuration value.
 *
 * @param string              $slug A unique identifier for the integration.
 * @param array<string,mixed> $options An associative options array for the integration.
 */
function activate( string $slug, array $options = [] ): void {
	IntegrationsSingleton::instance()->activate( $slug, $options );
}

/**
 * Check if a specific integration is enabled.
 *
 * @param string $slug A unique identifier for the integration.
 * @return bool True if integration is enabled, false otherwise.
 */
function wpvip_is_integration_enabled( string $slug ): bool {
	return IntegrationsSingleton::instance()->is_integration_enabled( $slug );
}

/**
 * Get a specific integration instance.
 *
 * @param string $slug A unique identifier for the integration.
 * @return Integration|null Integration instance if found and enabled, null otherwise.
 */
function wpvip_get_integration( string $slug ): ?Integration {
	return IntegrationsSingleton::instance()->get_integration( $slug );
}

/**
 * Get integration information including status and configuration.
 *
 * @param string $slug A unique identifier for the integration.
 * @return array|null Integration info array or null if not found.
 */
function wpvip_get_integration_info( string $slug ): ?array {
	return IntegrationsSingleton::instance()->get_integration_info( $slug );
}

/**
 * Get all enabled integrations.
 *
 * @return array<string,Integration> Array of enabled integrations keyed by slug.
 */
function wpvip_get_enabled_integrations(): array {
	return IntegrationsSingleton::instance()->get_enabled_integrations();
}

/**
 * Get all registered integrations (enabled and disabled).
 *
 * @return array<string,Integration> Array of all integrations keyed by slug.
 */
function wpvip_get_all_integrations(): array {
	return IntegrationsSingleton::instance()->get_all_integrations();
}

/**
 * Get a summary of all integrations with their status information.
 *
 * @return array<string,array> Summary array with integration info keyed by slug.
 */
function wpvip_get_integrations_summary(): array {
	return IntegrationsSingleton::instance()->get_integrations_summary();
}

// Load integrations in muplugins_loaded:5 to allow integrations to hook
// muplugins_loaded:10 or any later action.
add_action( 'muplugins_loaded', function () {
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
