<?php
/**
 * Test: Parse.ly Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\VIP\WP_Parsely_Integration\maybe_load_plugin;

/**
 * Test Class.
 */
class ParselyIntegrationTest extends WP_UnitTestCase {
	/**
	 * Slug of the integration.
	 *
	 * @var string
	 */
	private string $slug = 'parsely';
	/**
	 * Test registering integration and then activating it is loading integration.
	 */
	public function test__parsely_integration_is_loaded_if_not_defined_by_customer_and_vice_versa() {
		$parsely_integration = new ParselyIntegration( $this->slug );

		maybe_load_plugin();
		$parsely_integration->load( [] );

		if ( is_parsely_disabled() ) {
			$this->assertTrue( defined( 'VIP_PARSELY_ENABLED' ) );
			return;
		}

		$this->assertFalse( defined( 'VIP_PARSELY_ENABLED' ) );
	}
}
