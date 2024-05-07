<?php
/**
 * Test: Parse.ly Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class VIP_Parsely_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'parsely';

	public function test_is_loaded_returns_true_if_parsley_exist(): void {
		require_once __DIR__ . '/../../wp-parsely/wp-parsely.php';

		$parsely_integration = new ParselyIntegration( $this->slug );
		$this->assertTrue( $parsely_integration->is_loaded() );
	}

	public function test__load_call_returns_without_setting_constant_if_parsely_is_already_loaded(): void {
		/**
		 * Integration mock.
		 *
		 * @var MockObject|ParselyIntegration
		 */
		$parsely_integration_mock = $this->getMockBuilder( ParselyIntegration::class )->setConstructorArgs( [ 'parsely' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$parsely_integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( true );
		$preload_state = defined( 'VIP_PARSELY_ENABLED' );

		$parsely_integration_mock->load();

		$this->assertEquals( $preload_state, defined( 'VIP_PARSELY_ENABLED' ) );
	}

	public function test__load_call_is_setting_the_enabled_constant_if_no_constant_is_defined(): void {
		/**
		 * Integration mock.
		 *
		 * @var MockObject|ParselyIntegration
		 */
		$parsely_integration_mock = $this->getMockBuilder( ParselyIntegration::class )->setConstructorArgs( [ 'parsely' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$parsely_integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( false );
		$existing_value = defined( 'VIP_PARSELY_ENABLED' ) ? VIP_PARSELY_ENABLED : null;

		$parsely_integration_mock->load();

		if ( is_null( $existing_value ) || $existing_value ) {
			$this->assertTrue( VIP_PARSELY_ENABLED );
		} else {
			$this->assertFalse( defined( 'VIP_PARSELY_ENABLED' ) );
		}
	}

	public function test__configure_is_adding_necessary_hooks_need_for_configuration_on_vip_platform(): void {
		$parsely_integration = new ParselyIntegration( $this->slug );

		$parsely_integration->configure();

		$this->assertEquals( 10, has_filter( 'wp_parsely_credentials', [ $parsely_integration, 'wp_parsely_credentials_callback' ] ) );
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
}
