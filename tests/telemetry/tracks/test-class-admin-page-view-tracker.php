<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Integrations\FakeIntegrationWithAdminPageTracking;
use Automattic\VIP\Integrations\IntegrationsSingleton;
use Automattic\VIP\Telemetry\Telemetry_Event;
use Automattic\VIP\Telemetry\Telemetry_Event_Queue;
use Automattic\VIP\Telemetry\Telemetry_Test_Helpers;
use Automattic\VIP\Telemetry\Tracks;
use WP_UnitTestCase;
use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\VIP\Integrations\activate;

require_once __DIR__ . '/../../../vip-integrations.php';
require_once __DIR__ . '/../../integrations/fake-integration.php';
require_once __DIR__ . '/../trait-telemetry-test-helpers.php';

class Admin_Page_View_Tracker_Test extends WP_UnitTestCase {
	use Telemetry_Test_Helpers;

	/** @var \Automattic\VIP\Integrations\Integrations|null */
	private $original_integrations;

	public function setUp(): void {
		parent::setUp();

		get_class_property_as_public( Admin_Page_View_Tracker::class, 'tracks' )->setValue( null, null );

		$integrations_property       = get_class_property_as_public( IntegrationsSingleton::class, 'instance' );
		$this->original_integrations = $integrations_property->getValue();
		$integrations_property->setValue( null, null );
	}

	public function tearDown(): void {
		get_class_property_as_public( Admin_Page_View_Tracker::class, 'tracks' )->setValue( null, null );

		// Restore the integrations registered at bootstrap for other tests.
		get_class_property_as_public( IntegrationsSingleton::class, 'instance' )->setValue( null, $this->original_integrations );

		unset( $GLOBALS['current_screen'], $_SERVER['GEOIP_COUNTRY_CODE'] );
		parent::tearDown();
	}

	private function enable_tracked_integration(): void {
		IntegrationsSingleton::instance()->register( new FakeIntegrationWithAdminPageTracking( 'fake-test-integration' ) );
		activate( 'fake-test-integration' );
	}

	public function data_screens(): iterable {
		yield 'core screen, no tracked integration' => [ false, 'index.php', [], false ];
		yield 'core screen' => [ true, 'index.php', [], true ];
		yield 'integration screen' => [ true, 'toplevel_page_vip-block-governance', [], true ];
		yield 'screen added by the filter' => [ true, 'toplevel_page_my-plugin', [ 'toplevel_page_my-plugin' ], true ];
		yield 'screen not on the list' => [ true, 'tools.php', [], false ];
	}

	/**
	 * Editors' page views are recorded on listed screens, only when an integration is tracked.
	 *
	 * @dataProvider data_screens
	 */
	public function test_should_record_page_view_for_listed_screens( bool $has_tracked_integration, string $screen, array $extra_screens, bool $expected ): void {
		if ( $has_tracked_integration ) {
			$this->enable_tracked_integration();
		}
		$this->login_as( 'author' );
		$this->enable_vip_production_environment();
		add_filter( 'vip_telemetry_admin_page_view_screens', fn( $screens ) => array_merge( $screens, $extra_screens ) );

		$this->assertSame( $expected, Admin_Page_View_Tracker::should_record_page_view( $screen ) );
	}

	public function data_unsupported_environments(): iterable {
		yield 'not production' => [ [ 'VIP_GO_APP_ENVIRONMENT' => 'preprod' ] ];
		yield 'not VIP' => [ [ 'WPCOM_IS_VIP_ENV' => false ] ];
		yield 'sandboxed' => [ [ 'WPCOM_SANDBOXED' => true ] ];
		yield 'FedRAMP' => [ [ 'VIP_IS_FEDRAMP' => true ] ];
		yield 'opted out' => [ [ 'VIP_DISABLE_PENDO_TELEMETRY' => true ] ];
	}

	/**
	 * @dataProvider data_unsupported_environments
	 */
	public function test_should_not_record_page_view_in_unsupported_environments( array $overrides ): void {
		$this->enable_tracked_integration();
		$this->login_as( 'author' );
		$constants = array_merge( [
			'VIP_GO_APP_ENVIRONMENT' => 'production',
			'WPCOM_IS_VIP_ENV'       => true,
		], $overrides );
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$this->assertFalse( Admin_Page_View_Tracker::should_record_page_view( 'index.php' ) );
	}

	public function test_should_not_record_page_view_for_users_without_edit_posts(): void {
		$this->enable_tracked_integration();
		$this->login_as( 'subscriber' );
		$this->enable_vip_production_environment();

		$this->assertFalse( Admin_Page_View_Tracker::should_record_page_view( 'index.php' ) );
	}

	public function test_record_page_view_sends_tracks_event(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$this->enable_tracked_integration();
		$this->login_as( 'author' );
		$this->enable_vip_production_environment();
		set_current_screen( 'edit-post' );
		$_SERVER['GEOIP_COUNTRY_CODE'] = 'US';

		$queue = $this->createMock( Telemetry_Event_Queue::class );
		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with( $this->callback( function ( Telemetry_Event $event ) {
				$data = $event->get_data();

				$this->assertSame( 'vip_wordpress_admin_page_view', $data->_en );
				$this->assertSame( 'edit.php', $data->screen_id );
				$this->assertSame( 'post', $data->post_type );
				$this->assertSame( 'author', $data->user_role );
				$this->assertSame( 'US', $data->country_code );
				$this->assertFalse( isset( $data->taxonomy ) );

				return true;
			} ) )
			->willReturn( true );
		get_class_property_as_public( Admin_Page_View_Tracker::class, 'tracks' )->setValue( null, new Tracks( 'vip_wordpress_', [], $queue ) );

		Admin_Page_View_Tracker::record_page_view( 'edit.php' );
	}
}
