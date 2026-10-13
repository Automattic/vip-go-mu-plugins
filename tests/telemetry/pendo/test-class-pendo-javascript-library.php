<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Integrations\FakeIntegrationWithPendoTracking;
use Automattic\VIP\Integrations\IntegrationsSingleton;
use Automattic\VIP\Telemetry\Pendo\Pendo_JavaScript_Library;
use WP_UnitTestCase;
use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\VIP\Integrations\activate;
use function get_bloginfo;
use function wp_scripts;
use function wp_set_current_user;

require_once __DIR__ . '/../../../vip-integrations.php';
require_once __DIR__ . '/../../integrations/fake-integration.php';
require_once __DIR__ . '/../trait-telemetry-test-helpers.php';

class Pendo_JavaScript_Library_Test extends WP_UnitTestCase {
	use Telemetry_Test_Helpers;

	/** @var \Automattic\VIP\Integrations\Integrations|null */
	private $original_integrations;

	public function setUp(): void {
		parent::setUp();

		// Ensure singletons start fresh (protects against previous test's tearDown not running)
		// this is duplicative of the tearDown method, but without it tests are flaky.
		$pendo_property = get_class_property_as_public( Pendo_JavaScript_Library::class, 'instance' );
		$pendo_property->setValue( null, null );

		$integrations_property       = get_class_property_as_public( IntegrationsSingleton::class, 'instance' );
		$this->original_integrations = $integrations_property->getValue();
		$integrations_property->setValue( null, null );
	}

	public function tearDown(): void {
		// Reset Pendo_JavaScript_Library singleton (removes admin_enqueue_scripts hook)
		$pendo_property = get_class_property_as_public( Pendo_JavaScript_Library::class, 'instance' );
		$pendo_property->setValue( null, null );

		// Restore the original IntegrationsSingleton so the integrations registered at bootstrap aren't lost for other tests
		$integrations_property = get_class_property_as_public( IntegrationsSingleton::class, 'instance' );
		$integrations_property->setValue( null, $this->original_integrations );

		wp_deregister_script( 'vip-pendo-agent-script' );
		parent::tearDown();
	}

	private function enable_fake_integration_with_pendo_tracking(): void {
		$integration = new FakeIntegrationWithPendoTracking( 'fake-test-integration' );
		IntegrationsSingleton::instance()->register( $integration );
		activate( 'fake-test-integration' );
	}

	public function data_screens(): iterable {
		$screens = [
			'core screen'               => [ 'index.php', null, [], [] ],
			'allowed admin page'        => [ 'admin.php', 'vip-block-governance', [], [] ],
			'filter-allowed screen'     => [ 'newly-allowed-screen.php', null, [ 'newly-allowed-screen.php' ], [] ],
			'filter-allowed admin page' => [ 'admin.php', 'admin-page-slug', [], [ 'admin-page-slug' ] ],
		];

		foreach ( $screens as $name => $screen ) {
			yield "$name, no tracked integration" => [ false, ...$screen, false ];
			yield "$name, a tracked integration" => [ true, ...$screen, true ];
		}
	}

	/**
	 * Users with the edit_posts cap get the script on allowed screens, only when an integration is tracked.
	 *
	 * @dataProvider data_screens
	 */
	public function test_should_enqueue_script_for_allowed_screens( bool $has_tracked_integration, string $screen, ?string $page, array $extra_screens, array $extra_admin_screens, bool $expected ) {
		global $wp_query;

		if ( $has_tracked_integration ) {
			$this->enable_fake_integration_with_pendo_tracking();
		}

		$this->login_as( 'author' );
		$this->enable_pendo_environment();

		if ( null !== $page ) {
			$wp_query->query_vars['page'] = $page;
		}

		add_filter( 'vip_pendo_allowed_screens', fn( $screens ) => array_merge( $screens, $extra_screens ) );
		add_filter( 'vip_pendo_allowed_admin_screens', fn( $admin_screens ) => array_merge( $admin_screens, $extra_admin_screens ) );

		$this->assertSame( $expected, Pendo_JavaScript_Library::should_enqueue_script( $screen ) );
	}

	/**
	 * A screen that would get the script (see above) is skipped when Pendo is not enabled for the environment.
	 *
	 * @see Pendo_Test::test_is_pendo_enabled_for_environment()
	 */
	public function test_disabled_when_pendo_is_disabled_for_environment() {
		$this->enable_fake_integration_with_pendo_tracking();
		$this->login_as( 'author' );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'preprod' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
	}

	public function test_disabled_for_users_without_edit_post_cap() {
		$this->enable_fake_integration_with_pendo_tracking();
		// The role changes below, so this test can't use a shared user.
		$user = $this->factory()->user->create_and_get( [ 'role' => 'author' ] );
		wp_set_current_user( $user->ID );
		$this->enable_pendo_environment();

		$instance = Pendo_JavaScript_Library::init( 'test_api_key' );
		$instance->enqueue_scripts( 'index.php' );
		$this->assertTrue( wp_script_is( 'vip-pendo-agent-script', 'enqueued' ) );
		wp_dequeue_script( 'vip-pendo-agent-script' );
		wp_deregister_script( 'vip-pendo-agent-script' );

		$user->set_role( 'subscriber' );
		wp_set_current_user( 0 );
		wp_set_current_user( $user->ID );
		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'index.php' ) );
		$instance->enqueue_scripts( 'index.php' );
		$this->assertFalse( wp_script_is( 'vip-pendo-agent-script', 'registered' ) );
	}

	public function test_disabled_for_disallowed_screens() {
		// With a tracked integration, only the screen check can prevent loading.
		$this->enable_fake_integration_with_pendo_tracking();

		$this->login_as( 'author' );
		$this->enable_pendo_environment();

		$instance = Pendo_JavaScript_Library::init( 'test_api_key' );
		$instance->enqueue_scripts( 'index.php' );
		$this->assertTrue( wp_script_is( 'vip-pendo-agent-script', 'enqueued' ) );
		wp_dequeue_script( 'vip-pendo-agent-script' );
		wp_deregister_script( 'vip-pendo-agent-script' );

		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'non-allowed-screen.php' ) );
		$instance->enqueue_scripts( 'non-allowed-screen.php' );
		$this->assertFalse( wp_script_is( 'vip-pendo-agent-script', 'registered' ) );
	}

	public function test_disabled_for_disallowed_admin_screen() {
		global $wp_query;

		// With a tracked integration, only the screen check can prevent loading.
		$this->enable_fake_integration_with_pendo_tracking();

		$this->login_as( 'author' );
		$this->enable_pendo_environment();
		$wp_query->query_vars['page'] = 'vip-block-governance';
		$instance                     = Pendo_JavaScript_Library::init( 'test_api_key' );
		$instance->enqueue_scripts( 'admin.php' );
		$this->assertTrue( wp_script_is( 'vip-pendo-agent-script', 'enqueued' ) );
		wp_dequeue_script( 'vip-pendo-agent-script' );
		wp_deregister_script( 'vip-pendo-agent-script' );

		$wp_query->query_vars['page'] = 'disallowed-admin-page';
		$this->assertFalse( Pendo_JavaScript_Library::should_enqueue_script( 'admin.php' ) );
		$instance->enqueue_scripts( 'admin.php' );
		$this->assertFalse( wp_script_is( 'vip-pendo-agent-script', 'registered' ) );
	}

	public function test_should_return_singleton_instance() {
		$instance = Pendo_JavaScript_Library::init( 'test_api_key' );

		$this->assertSame( $instance, Pendo_JavaScript_Library::init( 'test_api_key' ) );
		$this->assertSame( $instance, Pendo_JavaScript_Library::init( 'test_api_key' ) );
	}

	public function data_initialization_users(): array {
		return [
			'regular user'     => [ 'Frances Ha', 'frances@ha.com', false, 'frances@ha.com' ],
			'VIP support user' => [ 'VIP User', 'vip@example.com', true, 'vip-vip@example.com' ],
		];
	}

	/**
	 * @dataProvider data_initialization_users
	 */
	public function test_initialization_data( string $display_name, string $email, bool $is_vip_support, string $expected_visitor_id ) {
		$this->enable_fake_integration_with_pendo_tracking();

		$user = $this->factory()->user->create_and_get( [
			'role'         => 'author',
			'display_name' => $display_name,
			'user_email'   => $email,
		] );
		if ( $is_vip_support ) {
			wp_roles()->add_role( 'vip_support', 'VIP Support' );
			$user->add_role( 'vip_support' );
		}
		wp_set_current_user( $user->ID );

		$this->enable_pendo_environment();
		Constant_Mocker::define( 'VIP_ORG_ID', 555 );
		Constant_Mocker::define( 'VIP_SF_ACCOUNT_ID', 111 );
		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', 'test_salt' );

		$instance = Pendo_JavaScript_Library::init( 'test_api_key' );
		$instance->enqueue_scripts( 'index.php' );

		$registered_scripts = wp_scripts()->registered;
		$this->assertArrayHasKey( 'vip-pendo-agent-script', $registered_scripts );

		$expected_data = [
			'apiKey'    => 'test_api_key',
			'account'   => [
				'id'         => '111',
				'vip_org_id' => '555',
				'wp_version' => get_bloginfo( 'version' ),
			],
			'env'       => 'io',
			'globalKey' => 'VIP_PENDO_MU_PLUGINS',
			'plugins'   => [],
			'visitor'   => [
				'id'             => $expected_visitor_id,
				'country_code'   => 'unknown',
				'email'          => $email,
				'full_name'      => $display_name,
				'role_wordpress' => 'author',
			],
		];

		$serialized_data = sprintf( 'var %s = %s;', 'VIP_PENDO_MU_PLUGINS_INIT_DATA', wp_json_encode( $expected_data ) );

		$this->assertSame( $serialized_data, $registered_scripts['vip-pendo-agent-script']->extra['data'] );
	}
}
