<?php
/**
 * Test: Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use ErrorException;
use WP_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\reset_custom_error_reporting;
use function Automattic\Test\Utils\setup_custom_error_reporting;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	/**
	 * Original error reporting.
	 *
	 * @var int
	 */
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->original_error_reporting = setup_custom_error_reporting();
	}

	public function tearDown(): void {
		reset_custom_error_reporting( $this->original_error_reporting );
		parent::tearDown();
	}

	public function test__activate_platform_integrations_are_activating_integrations_based_on_vip_configs(): void {
		$integrations        = new Integrations();
		$integration_1       = new FakeIntegration( 'fake-1' );
		$integration_2       = new FakeIntegration( 'fake-2' );
		$integration_3       = new FakeIntegration( 'fake-3' );
		$integrations_config = $this->set_integrations_config( [
			'fake-2' => [
				[
					'env' => [
						'status' => 'enabled',
						'config' => [ 'fake_2_key' => 'vip_value' ],
					],
				],
			],
		] );
		
		$integrations->register( $integration_1 );
		$integrations->register( $integration_2 );
		$integrations->register( $integration_3 );
		$integrations->activate( 'fake-1', [
			'option_key_1' => 'value',
			'config'       => [ 'fake_1_key' => 'value' ],
		] );
		$integrations->activate_platform_integrations( $integrations_config );

		$this->assertTrue( $integration_1->is_active() );
		$this->assertEquals( [ 'fake_1_key' => 'value' ], $integration_1->get_config() );
		$this->assertTrue( $integration_2->is_active() );
		$this->assertEquals(
			is_multisite() ? [] : [
				'type'       => 'fake-2',
				'fake_2_key' => 'vip_value',
			],
			$integration_2->get_config()
		);
		$this->assertFalse( $integration_3->is_active() );
		$this->assertEquals( [], $integration_3->get_config() );
	}

	public function test__load_active_is_loading_the_corresponding_integration(): void {
		$integrations = new Integrations();
		/**
		 * Integration mock .
		 *
		 * @var MockObject|Integration
		 */
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->onlyMethods( [ 'load' ] )->getMock();
		$integration_mock->expects( $this->once() )->method( 'load' );

		$integrations->register( $integration_mock );
		$integrations->activate( 'fake' );
		$integrations->load_active();

		$this->assertTrue( $integration_mock->is_active() );
	}

	public function test__load_active_does_not_loads_the_non_active_integration(): void {
		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->load_active();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__register_throws_invalidArgumentException_on_duplicate_registration(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Integration with slug "fake" is already registered.' );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->register( $integration );
	}

	public function test__register_throws_invalidArgumentException_when_arg_does_not_extend_integration_class(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Integration class "stdClass" must extend Automattic\VIP\Integrations\Integration.' );

		$integrations = new Integrations();
		$random_class = new stdClass();

		$integrations->register( $random_class );
	}

	public function test__activate_throws_invalidArgumentException_when_invalid_slug_is_passed(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'VIP Integration with slug "invalid-slug" is not a registered integration.' );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->activate( 'invalid-slug' );
	}

	/**
	 * Set config on VIPIntegrationsConfig and return it.
	 *
	 * @param array<mixed> $configs
	 *
	 * @return VipIntegrationsConfig
	 */
	private function set_integrations_config( $configs ) {
		$obj = new VipIntegrationsConfig();

		get_class_property_as_public( VipIntegrationsConfig::class, 'configs' )->setValue( $obj, $configs );

		return $obj;
	}
}
