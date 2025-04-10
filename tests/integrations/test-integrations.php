<?php
/**
 * Test: Integrations
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

use ErrorException;
use WP_UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integrations_Test extends WP_UnitTestCase {
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->original_error_reporting = error_reporting();
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( error_reporting() & $errno ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, $errno ); // NOSONAR
			}

			return false;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();
		error_reporting( $this->original_error_reporting );
		parent::tearDown();
	}

	public function test__integrations_are_activating_based_on_given_vip_config(): void {
		$config_mock = $this->getMockBuilder( IntegrationVipConfig::class )->disableOriginalConstructor()->onlyMethods( [ 'is_active_via_vip', 'get_env_config' ] )->getMock();
		$config_mock->expects( $this->exactly( 2 ) )->method( 'is_active_via_vip' )->willReturnOnConsecutiveCalls( true, false );
		$config_mock->expects( $this->exactly( 1 ) )->method( 'get_env_config' )->willReturnOnConsecutiveCalls( [ 'config_key_1' => 'vip_value' ] );

		/**
		 * Integrations mock.
		 *
		 * @var MockObject|Integrations
		 */
		$mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_integration_vip_config' ] )->getMock();
		$mock->expects( $this->any() )->method( 'get_integration_vip_config' )->willReturn( $config_mock );

		$integration_1 = new FakeIntegration( 'fake-1' );
		$integration_2 = new FakeIntegration( 'fake-2' );
		$integration_3 = new FakeIntegration( 'fake-3' );
		$mock->register( $integration_1 );
		$mock->register( $integration_2 );
		$mock->register( $integration_3 );
		$mock->activate( 'fake-1', [
			'option_key_1' => 'value',
			'config'       => [ 'config_key_1' => 'value' ],
		] );

		$mock->activate_platform_integrations();

		$this->assertTrue( $integration_1->is_active() );
		$this->assertEquals( [ 'config_key_1' => 'value' ], $integration_1->get_env_config() );
		$this->assertTrue( $integration_2->is_active() );
		$this->assertEquals( [ 'config_key_1' => 'vip_value' ], $integration_2->get_env_config() );
		$this->assertFalse( $integration_3->is_active() );
		$this->assertEquals( [], $integration_3->get_env_config() );
	}

	public function test__expected_methods_are_getting_called_when_the_integration_is_activated_via_vip_config(): void {
		$config_mock = $this->getMockBuilder( IntegrationVipConfig::class )->disableOriginalConstructor()->onlyMethods( [ 'is_active_via_vip' ] )->getMock();
		$config_mock->expects( $this->once() )->method( 'is_active_via_vip' )->willReturn( true );
		/**
		 * Integrations mock.
		 *
		 * @var MockObject|Integrations
		 */
		$integrations_mock = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_integration_vip_config' ] )->getMock();
		$integrations_mock->expects( $this->once() )->method( 'get_integration_vip_config' )->willReturn( $config_mock );
		/**
		 * Integration mock.
		 *
		 * @var MockObject|FakeIntegration
		 */
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->onlyMethods( [ 'configure', 'set_vip_config' ] )->getMock();
		$integration_mock->expects( $this->once() )->method( 'configure' );
		$integration_mock->expects( $this->once() )->method( 'set_vip_config' );

		$integrations_mock->register( $integration_mock );
		$integrations_mock->activate_platform_integrations();

		$this->assertTrue( $integration_mock->is_active() );
		$this->assertEquals( [], $integration_mock->get_env_config() );
	}

	public function test__get_integration_vip_config_returns_instance_of_IntegrationVipConfig(): void {
		$integrations = new Integrations();

		$integration_config = get_class_method_as_public( Integrations::class, 'get_integration_vip_config' )->invoke( $integrations, 'slug' );

		$this->assertInstanceOf( IntegrationVipConfig::class, $integration_config );
	}

	public function test__load_active_loads_the_activated_integration(): void {
		$integrations = new Integrations();

		$integration = new FakeIntegration( 'fake' );
		$integrations->register( $integration );
		$integrations->activate( 'fake' );
		$integrations->load_active();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__load_active_does_not_loads_the_non_activated_integration(): void {
		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->load_active();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__double_slug_registration_throws_invalidArgumentException(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Integration with slug "fake" is already registered.' );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->register( $integration );
	}

	public function test__non_integration_subclass_throws_invalidArgumentException(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Integration class "stdClass" must extend Automattic\VIP\Integrations\Integration.' );

		$integrations = new Integrations();
		$random_class = new stdClass();

		$integrations->register( $random_class );
	}

	public function test__activating_integration_by_passing_invalid_slug_throws_invalidArgumentException(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'VIP Integration with slug "invalid-slug" is not a registered integration.' );

		$integrations = new Integrations();
		$integration  = new FakeIntegration( 'fake' );

		$integrations->register( $integration );
		$integrations->activate( 'invalid-slug' );
	}
}
