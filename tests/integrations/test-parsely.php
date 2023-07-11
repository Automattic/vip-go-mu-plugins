<?php
/**
 * Test: Parse.ly Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\is_parsely_disabled;
use function Automattic\VIP\WP_Parsely_Integration\maybe_load_plugin;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Parsely_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'parsely';

	public function test__load_call_is_defining_the_enabled_constant_if_plugin_is_not_enabled_already(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );

		maybe_load_plugin();
		$parsely_integration->load( [] );

		if ( is_parsely_disabled() ) {
			$this->assertTrue( defined( 'VIP_PARSELY_ENABLED' ) );
			return;
		}

		$this->assertFalse( defined( 'VIP_PARSELY_ENABLED' ) ); // Indicates enablement via filter or option.
	}
}
