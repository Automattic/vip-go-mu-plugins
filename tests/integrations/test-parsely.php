<?php
/**
 * Test: Parse.ly Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\is_parsely_disabled;
use function Automattic\VIP\WP_Parsely_Integration\maybe_load_plugin;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class VIP_Parsely_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'parsely';

	public function test__load_call_is_defining_the_enabled_constant_and_adding_filter_if_plugin_is_not_enabled_already(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );

		maybe_load_plugin();
		$parsely_integration->load( [] );

		if ( is_parsely_disabled() ) {
			$this->assertTrue( defined( 'VIP_PARSELY_ENABLED' ) );
			$this->assertEquals( 10, has_filter( 'wp_parsely_credentials', [ $parsely_integration, 'wp_parsely_credentials_callback' ] ) );

			return;
		}

		// Indicates enablement via filter or option.
		$this->assertFalse( defined( 'VIP_PARSELY_ENABLED' ) );
		$this->assertFalse( has_filter( 'wp_parsely_credentials' ) );
	}

	public function test__wp_parsely_credentials_callback_returns_config_of_the_integration(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );
		get_class_property_as_public( Integration::class, 'config' )->setValue( $parsely_integration, [ 'configs' ] );

		$callback_value = get_class_method_as_public( ParselyIntegration::class, 'wp_parsely_credentials_callback' )->invoke( $parsely_integration );

		$this->assertEquals( [ 'configs' ], $callback_value );
	}
}
