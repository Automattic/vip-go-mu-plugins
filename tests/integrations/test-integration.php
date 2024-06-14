<?php
/**
 * Test: Integration
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

use Automattic\VIP\Integrations\IntegrationVipConfig;
use Env_Integration_Status;
use ErrorException;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

use function Automattic\Test\Utils\reset_custom_error_reporting;
use function Automattic\Test\Utils\setup_custom_error_reporting;

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Test extends WP_UnitTestCase {
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

	public function test__slug_is_setting_up_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
	}

	public function test__switch_blog_action_is_added_on_instantiation(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 10, has_action( 'switch_blog', [ $integration, 'switch_blog_callback' ] ) );
	}

	public function test__activate_is_marking_the_integration_as_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate();

		$this->assertTrue( $integration->is_active() );
	}

	public function test__activate_is_setting_up_the_plugins_config(): void {
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config' => [ 'config_test' ] ] );

		$this->assertEquals( [ 'config_test' ], $integration->get_config() );
	}

	public function test__calling_activate_when_the_integration_is_already_loaded_does_not_activate_the_integration_again(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Prevented activating of integration with slug "fake" because it is already loaded.' );
		/**
		 * Integration mock.
		 *
		 * @var MockObject|FakeIntegration
		 */
		$integration_mock = $this->getMockBuilder( FakeIntegration::class )->setConstructorArgs( [ 'fake' ] )->onlyMethods( [ 'is_loaded' ] )->getMock();
		$integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( true );

		$integration_mock->activate();

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test__calling_activate_twice_on_same_integration_does_not_activate_the_plugin_second_time(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'VIP Integration with slug "fake" is already activated.' );

		$integration = new FakeIntegration( 'fake' );

		$integration->activate();
		$integration->activate();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__switch_blog_callback_is_setting_the_correct_config_for_network_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite' );
		}

		$integration = new FakeIntegration( 'fake' );
		$integration->activate( [ 'config' => [ 'activate_config' ] ] );
		$blog_2_id = $this->factory()->blog->create_object( [ 'domain' => 'integration-test.site/2' ] );
		/**
		 * Intgration Config Mock.
		 *
		 * @var IntegrationVipConfig|MockObject
		 */
		$config_mock = $this->getMockBuilder( IntegrationVipConfig::class )->disableOriginalConstructor()->onlyMethods( [ 'get_vip_config_from_file' ] )->getMock();
		$config_mock->method( 'get_vip_config_from_file' )->willReturn( [
			'network_sites' => [
				get_current_blog_id() => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'network_site_1_config' ),
				],
				$blog_2_id            => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => array( 'network_site_2_config' ),
				],
			],
		] );
		$config_mock->__construct( 'slug' );
		$integration->set_vip_config( $config_mock );

		// By default return config passed via activate().
		$this->assertEquals( array( 'activate_config' ), $integration->get_config() );

		// If blog is switched then return config of current network site.
		switch_to_blog( $blog_2_id );
		$this->assertEquals( array( 'network_site_2_config' ), $integration->get_config() );

		// If blog is restored then return config of the main site.
		restore_current_blog();
		$this->assertEquals( array( 'network_site_1_config' ), $integration->get_config() );
	}

	public function test__is_active_returns_false_when_integration_is_not_active(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertFalse( $integration->is_active() );
	}

	public function test__set_vip_config_function_throws_error_if_integration_is_not_active(): void {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->expectExceptionMessage( 'Configuration info can only assigned if integration is active.' );

		$integration = new FakeIntegration( 'fake' );
		/**
		 * Intgration Config Mock.
		 *
		 * @var IntegrationVipConfig|MockObject
		 */
		$config_mock = $this->getMockBuilder( IntegrationVipConfig::class )->disableOriginalConstructor()->getMock();

		$this->assertFalse( $integration->set_vip_config( $config_mock ) );
	}
}
