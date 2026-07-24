<?php

/**
 * Test: Safe Publish Mirror Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use Env_Integration_Status;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Safe_Publish_Mirror_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'safe-publish-mirror';

	public function tearDown(): void {
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$integration = new SafePublishMirrorIntegration( $this->slug );
		$this->assertFalse( $integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_loaded_constant_is_defined(): void {
		Constant_Mocker::define( 'SAFE_PUBLISH_MIRROR_LOADED', true );

		$integration = new SafePublishMirrorIntegration( $this->slug );
		$this->assertTrue( $integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_plugin_file_constant_is_defined(): void {
		Constant_Mocker::define( 'SAFE_PUBLISH_MIRROR_PLUGIN_FILE', '/path/to/safe-publish-mirror.php' );

		$integration = new SafePublishMirrorIntegration( $this->slug );
		$this->assertTrue( $integration->is_loaded() );
	}

	public function test_configure_defines_single_config_constant_from_config(): void {
		$integration = new SafePublishMirrorIntegration( $this->slug );
		$integration->activate(
			[
				'config' => [
					'connected_site_url' => 'https://source.example.com',
					'sync_mode'          => 'import',
					'shared_secret'      => 'test-shared-secret',
					'version'            => '1.0',
				],
			]
		);
		$integration->configure();

		$this->assertSame(
			[
				'connected_site_url' => 'https://source.example.com',
				'sync_mode'          => 'import',
				'shared_secret'      => 'test-shared-secret',
			],
			constant( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG' )
		);
		$this->assertSame( '1.0', $integration->version );
	}

	public function test_configure_defines_constant_with_nulls_for_incomplete_config(): void {
		$integration = new SafePublishMirrorIntegration( $this->slug );
		$integration->activate(
			[
				'config' => [
					'connected_site_url' => 'https://source.example.com',
					'sync_mode'          => 'import',
				],
			]
		);
		$integration->configure();

		// The constant is always defined (even when required fields are missing) so
		// the plugin's config reader can report the incomplete setup gracefully.
		$this->assertSame(
			[
				'connected_site_url' => 'https://source.example.com',
				'sync_mode'          => 'import',
				'shared_secret'      => null,
			],
			constant( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG' )
		);
	}

	public function test_configure_does_not_redefine_existing_constant(): void {
		Constant_Mocker::define( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG', [ 'connected_site_url' => 'https://existing.example.com' ] );

		$integration = new SafePublishMirrorIntegration( $this->slug );
		$integration->activate(
			[
				'config' => [
					'connected_site_url' => 'https://new.example.com',
					'version'            => '1.0',
				],
			]
		);
		$integration->configure();

		$this->assertSame(
			[ 'connected_site_url' => 'https://existing.example.com' ],
			constant( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG' )
		);
		// Version selection is independent of the config constant, so a pinned
		// version must still be applied even when the constant is pre-defined.
		$this->assertSame( '1.0', $integration->version );
	}

	public function test_configure_merges_site_and_network_site_config_for_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$blog_2_id = $this->factory()->blog->create_object( [ 'domain' => 'safe-publish-mirror-test.site/2' ] );
		switch_to_blog( $blog_2_id );

		try {
			/** @var IntegrationVipConfig&MockObject $config_mock */
			$config_mock = $this->getMockBuilder( IntegrationVipConfig::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'get_vip_config_from_file' ] )
				->getMock();

			$config_mock->method( 'get_vip_config_from_file' )->willReturn(
				[
					'env'           => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [
							'version' => '1.0',
						],
					],
					'network_sites' => [
						1          => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [
								'connected_site_url' => 'https://site-one.example.com',
							],
						],
						$blog_2_id => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [
								'connected_site_url' => 'https://site-two.example.com',
								'sync_mode'          => 'export',
								'shared_secret'      => 'site-two-shared-secret',
							],
						],
					],
				]
			);
			$config_mock->__construct( $this->slug );

			$integration = new SafePublishMirrorIntegration( $this->slug );
			$integration->set_vip_config( $config_mock );
			$integration->configure();

			$this->assertSame(
				[
					'connected_site_url' => 'https://site-two.example.com',
					'sync_mode'          => 'export',
					'shared_secret'      => 'site-two-shared-secret',
				],
				constant( 'VIP_SAFE_PUBLISH_MIRROR_CONFIG' )
			);
			$this->assertSame( '1.0', $integration->version );
		} finally {
			restore_current_blog();
		}
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		/** @var MockObject|SafePublishMirrorIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( SafePublishMirrorIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->expects( $this->once() )
			->method( 'is_loaded' )
			->willReturn( true );

		$integration_mock->load();

		do_action( 'plugins_loaded' );
	}

	public function test_load_sets_inactive_when_no_versions_are_available(): void {
		/** @var MockObject|SafePublishMirrorIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( SafePublishMirrorIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_versions' ] )
			->getMock();

		$integration_mock->activate();
		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'get_versions' )->willReturn( [] );

		$integration_mock->load();

		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_latest(): void {
		$integration          = new SafePublishMirrorIntegration( $this->slug );
		$integration->version = 'latest';
		$versions             = [
			'safe-publish-mirror-2.5'  => '2.5',
			'safe-publish-mirror-1.11' => '1.11',
			'safe-publish-mirror-1.2'  => '1.2',
		];

		$this->assertSame( 'safe-publish-mirror-2.5', $integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_desired_version_when_version_is_specified(): void {
		$integration          = new SafePublishMirrorIntegration( $this->slug );
		$integration->version = '1.2';
		$versions             = [
			'safe-publish-mirror-2.5'  => '2.5',
			'safe-publish-mirror-1.11' => '1.11',
			'safe-publish-mirror-1.2'  => '1.2',
		];

		$this->assertSame( 'safe-publish-mirror-1.2', $integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_not_found(): void {
		$integration          = new SafePublishMirrorIntegration( $this->slug );
		$integration->version = '9.9';
		$versions             = [
			'safe-publish-mirror-2.5'  => '2.5',
			'safe-publish-mirror-1.11' => '1.11',
			'safe-publish-mirror-1.2'  => '1.2',
		];

		$this->assertSame( 'safe-publish-mirror-2.5', $integration->get_selected_version_folder( $versions ) );
	}
}
