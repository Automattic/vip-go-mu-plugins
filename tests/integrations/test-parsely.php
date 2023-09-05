<?php
/**
 * Test: Parse.ly Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\is_parsely_disabled;
use function Automattic\VIP\WP_Parsely_Integration\maybe_load_plugin;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class VIP_Parsely_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'parsely';

	public function test_is_integration_already_available_via_customer_returns_expected_value(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );

		if ( is_parsely_disabled() ) {
			$this->assertFalse( $parsely_integration->is_integration_already_available_via_customer() );
			return;
		}

		// Indicates enablement via filter or option.
		$this->assertTrue( $parsely_integration->is_integration_already_available_via_customer() );
	}

	public function test__configure_for_vip_is_adding_necessary_hooks_need_for_configuration_on_vip_platform(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );

		$parsely_integration->configure_for_vip();

		$this->assertEquals( 10, has_filter( 'wp_parsely_credentials', [ $parsely_integration, 'wp_parsely_credentials_callback' ] ) );
		$this->assertEquals( 10, has_filter( 'wp_parsely_managed_options', [ $parsely_integration, 'wp_parsely_managed_options_callback' ] ) );
	}

	public function test__load_call_is_defining_the_enabled_constant_if_plugin_is_not_loaded_already(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );

		maybe_load_plugin();
		$parsely_integration->load( [] );

		if ( is_parsely_disabled() ) {
			$this->assertTrue( defined( 'VIP_PARSELY_ENABLED' ) );
			return;
		}

		// Indicates enablement via filter or option.
		$this->assertFalse( defined( 'VIP_PARSELY_ENABLED' ) );
	}

	public function test__wp_parsely_credentials_callback_returns_original_credentials_of_the_integration_if_platform_config_is_empty(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );
		get_class_property_as_public( Integration::class, 'options' )->setValue( $parsely_integration, [
			'config' => [],
		] );

		$callback_value = $parsely_integration->wp_parsely_credentials_callback( [ 'credential_1' => 'value' ] );

		$this->assertEquals( [
			'is_managed'   => true,
			'credential_1' => 'value',
		], $callback_value );
	}

	public function test__wp_parsely_credentials_callback_returns_platform_credentials_of_the_integration_if_platform_config_exists(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );
		get_class_property_as_public( Integration::class, 'options' )->setValue( $parsely_integration, [
			'config' => [
				'site_id'     => 'value',
				'invalid_key' => 'value',
			],
		] );

		$callback_value = $parsely_integration->wp_parsely_credentials_callback( array() );

		$this->assertEquals( [
			'is_managed' => true,
			'site_id'    => 'value',
			'api_secret' => null,
		], $callback_value );
	}

	public function test__wp_parsely_managed_options_callback_returns_all_managed_options(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );
		$callback_value      = $parsely_integration->wp_parsely_managed_options_callback( false );

		$this->assertEquals( [
			'force_https_canonicals' => true,
			'meta_type'              => 'repeated_metas',
			'cats_as_tags'           => null,
			'content_id_prefix'      => null,
			'logo'                   => null,
			'lowercase_tags'         => null,
			'use_top_level_cats'     => null,
		], $callback_value );
	}
}
