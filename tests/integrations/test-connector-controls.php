<?php
/**
 * Test: Connector Controls integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use Env_Integration_Status;
use Org_Integration_Status;
use WP_UnitTestCase;

class Connector_Controls_Integration_Test extends WP_UnitTestCase {
	private const OPTIONS = [
		'connectors_ai_openai_api_key',
		'connectors_ai_anthropic_api_key',
		'connectors_ai_google_api_key',
	];

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( '\\wp_get_connectors' ) ) {
			$this->markTestSkipped( 'Requires the WordPress Connectors API.' );
		}
	}

	public function tearDown(): void {
		foreach ( self::OPTIONS as $option_name ) {
			remove_all_filters( "pre_option_{$option_name}" );
			remove_all_filters( "pre_update_option_{$option_name}" );
			delete_option( $option_name );
		}
		remove_all_filters( 'script_module_data_options-connectors-wp-admin' );
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_configure_defines_core_provider_constants(): void {
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->activate(
			[
				'config' => [
					'openai_api_key'    => 'openai-secret',
					'anthropic_api_key' => 'anthropic-secret',
					'google_api_key'    => 'google-secret',
				],
			]
		);

		$integration->configure();

		$this->assertSame( 'openai-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		$this->assertSame( 'anthropic-secret', Constant_Mocker::constant( 'ANTHROPIC_API_KEY' ) );
		$this->assertSame( 'google-secret', Constant_Mocker::constant( 'GOOGLE_API_KEY' ) );
		$this->assertTrue( $integration->is_loaded() );
	}

	public function test_configure_does_not_override_an_existing_constant(): void {
		Constant_Mocker::define( 'OPENAI_API_KEY', 'customer-secret' );
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->activate( [ 'config' => [ 'openai_api_key' => 'platform-secret' ] ] );

		$integration->configure();

		$this->assertSame( 'customer-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
	}

	public function test_environment_credentials_override_organization_credentials(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'environment-secret' ],
				],
			]
		);
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->set_vip_config( $config );

		$integration->configure();

		$this->assertSame( 'environment-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
	}

	public function test_environment_can_explicitly_disable_an_organization_credential(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::ENABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
				'env' => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'openai_blocked' => 'true' ],
				],
			]
		);
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->set_vip_config( $config );

		$integration->configure();

		$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
	}

	public function test_disabled_organization_does_not_configure_credentials(): void {
		$config      = new IntegrationVipConfig(
			'connector-controls',
			[
				'org' => [
					'status' => Org_Integration_Status::DISABLED,
					'config' => [ 'openai_api_key' => 'org-secret' ],
				],
			]
		);
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->set_vip_config( $config );
		$integration->activate();

		$integration->configure();

		$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		$this->assertFalse( $integration->is_active() );
	}

	public function test_network_site_credentials_override_environment_credentials(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$blog_id = $this->factory()->blog->create_object( [ 'domain' => 'connector-controls.test/2' ] );
		switch_to_blog( $blog_id );

		try {
			$config      = new IntegrationVipConfig(
				'connector-controls',
				[
					'org'           => [
						'status' => Org_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'org-secret' ],
					],
					'env'           => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'environment-secret' ],
					],
					'network_sites' => [
						$blog_id => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'openai_api_key' => 'network-secret' ],
						],
					],
				]
			);
			$integration = new ConnectorControlsIntegration( 'connector-controls' );
			$integration->set_vip_config( $config );

			$integration->configure();

			$this->assertSame( 'network-secret', Constant_Mocker::constant( 'OPENAI_API_KEY' ) );
		} finally {
			restore_current_blog();
		}
	}

	public function test_network_site_can_explicitly_disable_an_environment_credential(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$blog_id = $this->factory()->blog->create_object( [ 'domain' => 'connector-controls.test/3' ] );
		switch_to_blog( $blog_id );

		try {
			$config      = new IntegrationVipConfig(
				'connector-controls',
				[
					'org'           => [
						'status' => Org_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'org-secret' ],
					],
					'env'           => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'openai_api_key' => 'environment-secret' ],
					],
					'network_sites' => [
						$blog_id => [
							'status' => Env_Integration_Status::ENABLED,
							'config' => [ 'openai_blocked' => 'true' ],
						],
					],
				]
			);
			$integration = new ConnectorControlsIntegration( 'connector-controls' );
			$integration->set_vip_config( $config );

			$integration->configure();

			$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		} finally {
			restore_current_blog();
		}
	}

	public function test_managed_database_options_are_inert_and_cannot_be_updated(): void {
		update_option( 'connectors_ai_openai_api_key', 'database-secret' );
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$integration->configure();
		add_filter(
			'pre_option_connectors_ai_openai_api_key',
			static function () {
				return 'leaked-secret';
			},
			100
		);
		add_filter(
			'pre_update_option_connectors_ai_openai_api_key',
			static function ( $new_value ) {
				return $new_value;
			},
			100
		);

		$this->assertSame( '', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertFalse( update_option( 'connectors_ai_openai_api_key', 'replacement-secret' ) );

		remove_all_filters( 'pre_option_connectors_ai_openai_api_key' );
		$this->assertSame( 'database-secret', get_option( 'connectors_ai_openai_api_key' ) );
	}

	public function test_connector_fields_are_locked_without_changing_existing_external_sources(): void {
		$integration = new ConnectorControlsIntegration( 'connector-controls' );
		$data        = [
			'connectors' => [
				'openai'    => [ 'authentication' => [ 'keySource' => 'database' ] ],
				'anthropic' => [ 'authentication' => [ 'keySource' => 'env' ] ],
				'custom'    => [ 'authentication' => [ 'keySource' => 'database' ] ],
			],
		];

		$filtered = $integration->lock_connector_fields( $data );

		$this->assertSame( 'constant', $filtered['connectors']['openai']['authentication']['keySource'] );
		$this->assertSame( 'env', $filtered['connectors']['anthropic']['authentication']['keySource'] );
		$this->assertSame( 'database', $filtered['connectors']['custom']['authentication']['keySource'] );
	}
}

class Connector_Controls_Without_Core_API_Integration extends ConnectorControlsIntegration {
	protected function is_connectors_api_available(): bool {
		return false;
	}
}

class Connector_Controls_Unsupported_Core_Integration_Test extends WP_UnitTestCase {
	public function tearDown(): void {
		remove_all_filters( 'pre_option_connectors_ai_openai_api_key' );
		remove_all_filters( 'pre_update_option_connectors_ai_openai_api_key' );
		delete_option( 'connectors_ai_openai_api_key' );
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_configure_does_not_mutate_runtime_without_the_connectors_api(): void {
		update_option( 'connectors_ai_openai_api_key', 'database-secret' );
		$integration = new Connector_Controls_Without_Core_API_Integration( 'connector-controls' );
		$integration->activate( [ 'config' => [ 'openai_api_key' => 'platform-secret' ] ] );

		$integration->configure();

		$this->assertFalse( $integration->is_active() );
		$this->assertFalse( Constant_Mocker::defined( 'OPENAI_API_KEY' ) );
		$this->assertSame( 'database-secret', get_option( 'connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'pre_option_connectors_ai_openai_api_key' ) );
		$this->assertFalse( has_filter( 'pre_update_option_connectors_ai_openai_api_key' ) );
	}
}
