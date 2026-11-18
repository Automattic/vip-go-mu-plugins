<?php

/**
 * Test: Safe Publish Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use Env_Integration_Status;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

require_once __DIR__ . '/trait-secondary-blog.php';

class Safe_Publish_Integration_Test extends WP_UnitTestCase {
	use Secondary_Blog;

	private string $slug = 'safe-publish';

	public function test_configure_defines_safe_publish_constants_from_config(): void {
		$safe_publish_integration = new SafePublishIntegration( $this->slug );
		$safe_publish_integration->activate(
			[
				'config' => [
					'connected_site_url'  => 'https://source.example.com',
					'sync_mode'           => 'import',
					'shared_secret'       => 'test-shared-secret',
					'basic_auth_username' => 'publisher',
					'basic_auth_password' => 'password',
					'version'             => '1.0',
				],
			]
		);
		$safe_publish_integration->configure();

		$this->assertSame( 'https://source.example.com', constant( 'SAFE_PUBLISH_CONNECTED_SITE_URL' ) );
		$this->assertSame( 'import', constant( 'SAFE_PUBLISH_SYNC_MODE' ) );
		$this->assertSame( 'test-shared-secret', constant( 'SAFE_PUBLISH_SHARED_SECRET' ) );
		$this->assertSame( 'publisher', constant( 'SAFE_PUBLISH_BASIC_AUTH_USERNAME' ) );
		$this->assertSame( 'password', constant( 'SAFE_PUBLISH_BASIC_AUTH_PASSWORD' ) );
		$this->assertSame( '1.0', $safe_publish_integration->version );
	}

	public function test_configure_does_not_redefine_existing_constants(): void {
		Constant_Mocker::define( 'SAFE_PUBLISH_CONNECTED_SITE_URL', 'https://existing.example.com' );

		$safe_publish_integration = new SafePublishIntegration( $this->slug );
		$safe_publish_integration->activate(
			[
				'config' => [
					'connected_site_url' => 'https://new.example.com',
				],
			]
		);
		$safe_publish_integration->configure();

		$this->assertSame( 'https://existing.example.com', constant( 'SAFE_PUBLISH_CONNECTED_SITE_URL' ) );
	}

	/**
	 * On multisite, the current network site's config is merged over the environment config.
	 *
	 * @dataProvider data_multisite_config
	 */
	public function test_configure_merges_environment_and_network_site_config_for_multisite( array $env_config, array $site_config, array $expected_constants, string $expected_version ): void {
		$blog_2_id = $this->switch_to_secondary_blog();

		$config = new IntegrationVipConfig(
			$this->slug,
			[
				'env'           => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => $env_config,
				],
				'network_sites' => [
					1          => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [
							'connected_site_url' => 'https://site-one.example.com',
							'sync_mode'          => 'import',
							'shared_secret'      => 'site-one-shared-secret',
							'version'            => '1.5',
						],
					],
					$blog_2_id => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => $site_config,
					],
				],
			]
		);

		$safe_publish_integration = new SafePublishIntegration( $this->slug );
		$safe_publish_integration->set_vip_config( $config );
		$safe_publish_integration->configure();

		foreach ( $expected_constants as $constant_name => $expected ) {
			$this->assertSame( $expected, constant( $constant_name ), $constant_name );
		}
		$this->assertSame( $expected_version, $safe_publish_integration->version );
	}

	public static function data_multisite_config(): array {
		$site_config = [
			'connected_site_url' => 'https://site-two.example.com',
			'sync_mode'          => 'export',
			'shared_secret'      => 'site-two-shared-secret',
		];

		return [
			'shared values come from the environment'    => [
				[
					'basic_auth_username' => 'env-publisher',
					'basic_auth_password' => 'env-password',
					'version'             => '1.0',
				],
				$site_config,
				[
					'SAFE_PUBLISH_CONNECTED_SITE_URL'  => 'https://site-two.example.com',
					'SAFE_PUBLISH_SYNC_MODE'           => 'export',
					'SAFE_PUBLISH_SHARED_SECRET'       => 'site-two-shared-secret',
					'SAFE_PUBLISH_BASIC_AUTH_USERNAME' => 'env-publisher',
					'SAFE_PUBLISH_BASIC_AUTH_PASSWORD' => 'env-password',
				],
				'1.0',
			],
			'network site values win for duplicate keys' => [
				[
					'connected_site_url'  => 'https://env-source.example.com',
					'sync_mode'           => 'import',
					'shared_secret'       => 'env-shared-secret',
					'basic_auth_username' => 'env-publisher',
					'basic_auth_password' => 'env-password',
					'version'             => '1.0',
				],
				array_merge(
					$site_config,
					[
						'basic_auth_username' => 'site-two-publisher',
						'basic_auth_password' => 'site-two-password',
						'version'             => '2.0',
					]
				),
				[
					'SAFE_PUBLISH_CONNECTED_SITE_URL'  => 'https://site-two.example.com',
					'SAFE_PUBLISH_SYNC_MODE'           => 'export',
					'SAFE_PUBLISH_SHARED_SECRET'       => 'site-two-shared-secret',
					'SAFE_PUBLISH_BASIC_AUTH_USERNAME' => 'site-two-publisher',
					'SAFE_PUBLISH_BASIC_AUTH_PASSWORD' => 'site-two-password',
				],
				'2.0',
			],
		];
	}
}
