<?php

/**
 * Test: Safe Publish Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Safe_Publish_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'safe-publish';

	public function tearDown(): void {
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$safe_publish_integration = new SafePublishIntegration( $this->slug );
		$this->assertFalse( $safe_publish_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_loaded_constant_is_defined(): void {
		Constant_Mocker::define( 'SAFE_PUBLISH_LOADED', true );

		$safe_publish_integration = new SafePublishIntegration( $this->slug );
		$this->assertTrue( $safe_publish_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_plugin_file_constant_is_defined(): void {
		Constant_Mocker::define( 'SAFE_PUBLISH_PLUGIN_FILE', '/path/to/safe-publish.php' );

		$safe_publish_integration = new SafePublishIntegration( $this->slug );
		$this->assertTrue( $safe_publish_integration->is_loaded() );
	}

	public function test_configure_defines_safe_publish_constants_from_config(): void {
		/** @var MockObject|SafePublishIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( SafePublishIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'connected_site_url'  => 'https://source.example.com',
			'sync_mode'           => 'import',
			'shared_secret'       => 'test-shared-secret',
			'basic_auth_username' => 'publisher',
			'basic_auth_password' => 'password',
			'version'             => '1.0',
		] );

		$integration_mock->configure();

		$this->assertSame( 'https://source.example.com', constant( 'SAFE_PUBLISH_CONNECTED_SITE_URL' ) );
		$this->assertSame( 'import', constant( 'SAFE_PUBLISH_SYNC_MODE' ) );
		$this->assertSame( 'test-shared-secret', constant( 'SAFE_PUBLISH_SHARED_SECRET' ) );
		$this->assertSame( 'publisher', constant( 'SAFE_PUBLISH_BASIC_AUTH_USERNAME' ) );
		$this->assertSame( 'password', constant( 'SAFE_PUBLISH_BASIC_AUTH_PASSWORD' ) );
		$this->assertSame( '1.0', $integration_mock->version );
	}

	public function test_configure_does_not_redefine_existing_constants(): void {
		Constant_Mocker::define( 'SAFE_PUBLISH_CONNECTED_SITE_URL', 'https://existing.example.com' );

		/** @var MockObject|SafePublishIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( SafePublishIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'connected_site_url' => 'https://new.example.com',
		] );

		$integration_mock->configure();

		$this->assertSame( 'https://existing.example.com', constant( 'SAFE_PUBLISH_CONNECTED_SITE_URL' ) );
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		/** @var MockObject|SafePublishIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( SafePublishIntegration::class )
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
		/** @var MockObject|SafePublishIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( SafePublishIntegration::class )
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
		$safe_publish_integration          = new SafePublishIntegration( $this->slug );
		$safe_publish_integration->version = 'latest';
		$versions                          = [
			'safe-publish-2.5'  => '2.5',
			'safe-publish-1.11' => '1.11',
			'safe-publish-1.2'  => '1.2',
		];

		$this->assertSame( 'safe-publish-2.5', $safe_publish_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_desired_version_when_version_is_specified(): void {
		$safe_publish_integration          = new SafePublishIntegration( $this->slug );
		$safe_publish_integration->version = '1.2';
		$versions                          = [
			'safe-publish-2.5'  => '2.5',
			'safe-publish-1.11' => '1.11',
			'safe-publish-1.2'  => '1.2',
		];

		$this->assertSame( 'safe-publish-1.2', $safe_publish_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_not_found(): void {
		$safe_publish_integration          = new SafePublishIntegration( $this->slug );
		$safe_publish_integration->version = '9.9';
		$versions                          = [
			'safe-publish-2.5'  => '2.5',
			'safe-publish-1.11' => '1.11',
			'safe-publish-1.2'  => '1.2',
		];

		$this->assertSame( 'safe-publish-2.5', $safe_publish_integration->get_selected_version_folder( $versions ) );
	}
}
