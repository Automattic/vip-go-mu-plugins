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
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

require_once __DIR__ . '/fake-integration.php';
require_once __DIR__ . '/trait-secondary-blog.php';

class VIP_Integration_Test extends WP_UnitTestCase {
	use Secondary_Blog;

	public function test__manual_activation_uses_the_passed_in_config(): void {
		$integration = new FakeIntegration( 'fake' );

		$this->assertEquals( 'fake', $integration->get_slug() );
		$this->assertFalse( $integration->is_active() );

		$integration->activate( [ 'config' => [ 'config_test' ] ] );

		$this->assertTrue( $integration->is_active() );
		$this->assertEquals( [ 'config_test' ], $integration->get_env_config() );
		$this->assertEquals( [ 'config_test' ], $integration->get_network_site_config() );
		$this->assertEquals( [], $integration->get_child_configs() );
		$this->assertEquals( [], $integration->get_child_env_configs() );
	}

	public function test__calling_activate_when_the_integration_is_already_loaded_does_not_activate_the_integration_again(): void {
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
		$integration = new FakeIntegration( 'fake' );

		$integration->activate( [ 'config' => [ 'test_config' ] ] );
		$integration->activate( [ 'config' => [ 'updated_config' ] ] );

		$this->assertTrue( $integration->is_active() );
		$this->assertEquals( [ 'test_config' ], $integration->get_env_config() );
	}

	public function test__switch_to_blog_is_getting_the_correct_config_for_network_site(): void {
		$this->skipWithoutMultisite();

		$integration = new FakeIntegration( 'fake' );
		$integration->activate( [ 'config' => [ 'activate_config' ] ] );
		$blog_2_id = self::$secondary_blog_id;
		$integration->set_vip_config( new IntegrationVipConfig( 'slug', [
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
		] ) );

		// The vip config overrides the custom passed-in config when set.
		$this->assertEquals( array( 'network_site_1_config' ), $integration->get_network_site_config() );

		// If blog is switched then return config of current network site.
		switch_to_blog( $blog_2_id );
		$this->assertEquals( array( 'network_site_2_config' ), $integration->get_network_site_config() );

		// If blog is restored then return config of the main site.
		restore_current_blog();
		$this->assertEquals( array( 'network_site_1_config' ), $integration->get_network_site_config() );
	}

	public function test__get_child_configs_delegates_to_vip_config_when_present(): void {
		$children_config = [
			'airtable' => [
				'type' => 'airtable',
				'env'  => [
					'status' => 'enabled',
					'config' => [ 'sources' => [ [ 'uuid' => 'test-1' ] ] ],
				],
			],
		];

		$integration = new FakeIntegration( 'fake' );
		$integration->activate();
		$integration->set_vip_config( new IntegrationVipConfig( 'fake', [ 'children' => $children_config ] ) );

		$this->assertEquals( $children_config, $integration->get_child_configs() );
		$this->assertEquals( [ 'airtable' => [ 'sources' => [ [ 'uuid' => 'test-1' ] ] ] ], $integration->get_child_env_configs() );
	}

	public function test__should_track_in_pendo_follows_the_integration_setting(): void {
		$this->assertFalse( ( new FakeIntegration( 'fake' ) )->should_track_in_pendo() );
		$this->assertTrue( ( new FakeIntegrationWithPendoTracking( 'fake' ) )->should_track_in_pendo() );
	}
}
