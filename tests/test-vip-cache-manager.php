<?php

use Automattic\Test\Utils\Captures_Errors;

use function Automattic\Test\Utils\get_class_method_as_public;
use function Automattic\Test\Utils\get_class_property_as_public;

require_once __DIR__ . '/fixtures/class-cache-manager-input-stream.php';

class VIP_Go_Cache_Manager_Test extends WP_UnitTestCase {
	use Captures_Errors;

	/** @var WPCOM_VIP_Cache_Manager */
	public $cache_manager;

	public function setUp(): void {
		parent::setUp();

		$this->cache_manager = WPCOM_VIP_Cache_Manager::instance();
		$this->cache_manager->init();
		$this->cache_manager->clear_queued_purge_urls();
		$this->reset_cache_manager_state();
	}

	public function tearDown(): void {
		$this->reset_cache_manager_state();
		parent::tearDown();
	}

	/**
	 * Call the private WPCOM_VIP_Cache_Manager::current_user_can_purge_cache().
	 *
	 * @param string ...$scope Optional purge scope; omitted to use the method's default.
	 */
	private function can_purge( string ...$scope ): bool {
		return get_class_method_as_public( WPCOM_VIP_Cache_Manager::class, 'current_user_can_purge_cache' )->invoke( $this->cache_manager, ...$scope );
	}

	public function get_data_for_valid_queue_purge_url_test() {
		return [
			// 1: input URL
			// 2: array of expected purge_urls list

			'normal_url'                     => [
				'http://example.com/path/to/files',
				[ 'http://example.com/path/to/files' ],
			],

			'strip_querystring'              => [
				'https://example.com/path/to/file?query',
				[ 'https://example.com/path/to/file' ],
			],

			'strip_fragment'                 => [
				'https://example.com/post#fragment',
				[ 'https://example.com/post' ],
			],

			'strip_querystring_and_fragment' => [
				'https://example.com/post?query#fragment',
				[ 'https://example.com/post' ],
			],
		];
	}

	public function get_data_for_invalid_queue_purge_url_test() {
		return [
			'invalid_scheme' => [
				'badscheme://example.com/path',
			],
		];
	}

	/**
	 * Tests valid URL inputs for `queue_purge_url`
	 *
	 * @dataProvider get_data_for_valid_queue_purge_url_test
	 */
	public function test__valid__queue_purge_url( $queue_url, $expected_urls ) {
		$actual_output = $this->cache_manager->queue_purge_url( $queue_url );

		$this->assertTrue( $actual_output, 'Return value from `queue_purge_url` does not match.' );
		$this->assertEquals( $expected_urls, $this->cache_manager->get_queued_purge_urls(), 'List of queued purge urls do not match' );
	}

	/**
	 * Tests invalid URL inputs for `queue_purge_url`
	 *
	 * They are all expected to return false, queue nothing, and throw a warning.
	 *
	 * @dataProvider get_data_for_invalid_queue_purge_url_test
	 */
	public function test__invalid__queue_purge_url( $queue_url ) {
		[ $result, $warnings ] = $this->capture_errors( fn() => $this->cache_manager->queue_purge_url( $queue_url ) );

		self::assertFalse( $result );
		self::assertSame( [ 'vip-cache-manager: Tried to PURGE invalid URL: ' . esc_html( $queue_url ) ], $warnings );
		self::assertEmpty( $this->cache_manager->get_queued_purge_urls(), 'List of queued purge urls should be empty' );
	}

	public function test__page_for_posts_post_purge_url() {
		$page_for_posts = $this->factory()->post->create_and_get(
			[
				'post_type'  => 'page',
				'post_title' => 'blog-archive',
			]
		);
		update_option( 'page_for_posts', $page_for_posts->ID );
		$permalink = get_permalink( $page_for_posts );

		$post = (array) $this->factory()->post->create_and_get( [ 'post_title' => 'test post' ] );

		$post['post_title'] = 'updated';

		wp_update_post( $post );

		$this->assertIsArray( $this->cache_manager->get_queued_purge_urls(), 'Queued purge urls variable is an array' );

		$this->assertContains( $permalink, $this->cache_manager->get_queued_purge_urls(), 'Queued purge urls should contain page_for_posts permlink' );
	}

	public function get_data_for_special_purge_actions() {
		return [
			'origin'  => [ 'purge_origin_cache', '/.vip/purge-all-origin' ],
			'uploads' => [ 'purge_uploads_cache', '/.vip/purge-all-uploads' ],
			'static'  => [ 'purge_static_files_cache', '/.vip/purge-all-static-files' ],
			'private' => [ 'purge_private_files_cache', '/.vip/purge-all-private-files' ],
		];
	}

	/**
	 * @dataProvider get_data_for_special_purge_actions
	 */
	public function test_special_purge_actions_queue_expected_urls( $method, $path ) {
		$result = $this->cache_manager->{$method}();
		$this->assertTrue( $result, 'Special purge method should return true.' );

		$expected_url = trailingslashit( home_url() ) . ltrim( $path, '/' );
		$this->assertContains( $expected_url, $this->cache_manager->get_queued_purge_urls(), 'Expected purge URL missing.' );

		$this->cache_manager->clear_queued_purge_urls();
	}

	public function test_purge_site_cache_returns_false_after_first_call() {
		$this->assertTrue( $this->cache_manager->purge_site_cache(), 'First site purge should return true.' );
		$this->assertFalse( $this->cache_manager->purge_site_cache(), 'Subsequent site purge should return false for same request.' );
	}

	/**
	 * Default purge permissions must follow the user's editing capabilities.
	 */
	public function test_default_purge_permissions(): void {
		// [ role, or null when logged out; whether the default allows purging ]
		foreach ( [
			[ null, false ],
			[ 'subscriber', false ],
			[ 'editor', true ],
		] as [ $role, $allowed ] ) {
			wp_set_current_user( $role ? self::factory()->user->create( [ 'role' => $role ] ) : 0 );
			$this->assertSame( $allowed, $this->can_purge( 'url' ), $role ?: 'logged out' );
		}
	}

	/**
	 * Supply denied and allowed users for the real AJAX action.
	 */
	public function get_ajax_permission_cases(): array {
		return [
			'subscriber' => [ 'subscriber', false ],
			'editor'     => [ 'editor', true ],
		];
	}

	/**
	 * Dispatch the production AJAX hook with a valid request.
	 *
	 * @dataProvider get_ajax_permission_cases
	 */
	public function test_ajax_permissions_with_valid_nonce( string $role, bool $allowed ): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
		VIP_Cache_Manager_Input_Stream::$body = wp_json_encode( [
			'nonce'        => wp_create_nonce( 'vip_cache_manager_dashboard_purge' ),
			'purge_action' => 'url',
			'url'          => home_url( '/cache-permission-test/' ),
		] );
		$die_handler                          = static function () {
			return static function () {
				throw new RuntimeException( 'AJAX complete' );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $die_handler );
		stream_wrapper_unregister( 'php' );
		stream_wrapper_register( 'php', VIP_Cache_Manager_Input_Stream::class );
		ob_start();
		try {
			do_action( 'wp_ajax_vip_cache_manager_dashboard_purge' );
			$this->fail( 'The AJAX response must terminate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'AJAX complete', $exception->getMessage() );
		} finally {
			$response = ob_get_clean();
			stream_wrapper_restore( 'php' );
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}
		$decoded = json_decode( $response, true );
		$this->assertSame( $allowed, $decoded['success'] );
		if ( $allowed ) {
			$this->assertContains( home_url( '/cache-permission-test/' ), $this->cache_manager->get_queued_purge_urls() );
		} else {
			$this->assertSame( [ 'message' => 'Unauthorized.' ], $decoded['data'] );
			$this->assertEmpty( $this->cache_manager->get_queued_purge_urls() );
		}
	}

	public function test_current_user_can_purge_cache_filter() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$calls = [];
		add_filter( 'vip_cache_manager_can_purge_cache', static function ( $can_purge_cache, $user, $scope ) use ( &$calls ) {
			$calls[] = [ $can_purge_cache, $user, $scope ];

			return 'url' === $scope;
		}, 10, 3 );

		$this->assertTrue( $this->can_purge( 'url' ), 'URL scope should be allowed by the filter callback.' );
		$this->assertFalse( $this->can_purge( 'site' ), 'Non-URL scope should be denied by the filter callback.' );
		$this->assertFalse( $this->can_purge(), 'No scope should be denied by the filter callback.' );

		[ $can_purge_cache, $user, $scope ] = $calls[0];
		$this->assertFalse( $can_purge_cache, 'Subscribers cannot purge the cache by default.' );
		$this->assertInstanceOf( WP_User::class, $user, 'Current user should be passed to the permission filter.' );
		$this->assertSame( $user_id, $user->ID, 'Permission filter should receive the current user ID.' );
		$this->assertSame( 'url', $scope, 'Scope should be passed to the permission filter.' );
		$this->assertSame( 'site', $calls[1][2], 'Scope should be passed to the permission filter.' );
		$this->assertNull( $calls[2][2], 'Scope should default to null when no specific purge context is provided.' );
	}

	public function test_render_dashboard_widget_dropdown_only_shows_allowed_scopes() {
		$callback = static function ( $can_purge_cache, $user, $scope ) {
			return in_array( $scope, [ 'url', 'origin' ], true );
		};

		add_filter( 'vip_cache_manager_can_purge_cache', $callback, 10, 3 );

		try {
			ob_start();
			$this->cache_manager->render_dashboard_widget();
			$widget_html = (string) ob_get_clean();
		} finally {
			remove_filter( 'vip_cache_manager_can_purge_cache', $callback, 10 );
		}

		$this->assertStringContainsString( 'value="url"', $widget_html, 'URL action should be visible when URL scope is allowed.' );
		$this->assertStringContainsString( 'value="origin"', $widget_html, 'Origin action should be visible when origin scope is allowed.' );
		$this->assertStringNotContainsString( 'value="site"', $widget_html, 'Site action should be hidden when site scope is denied.' );
		$this->assertStringNotContainsString( 'value="uploads"', $widget_html, 'Uploads action should be hidden when uploads scope is denied.' );
		$this->assertStringNotContainsString( 'value="static"', $widget_html, 'Static action should be hidden when static scope is denied.' );
		$this->assertStringNotContainsString( 'value="private"', $widget_html, 'Private files action should be hidden when private scope is denied.' );
	}

	public function test_enqueue_dashboard_widget_assets_localizes_confirmation_guardrail_data() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->cache_manager->enqueue_dashboard_widget_assets( 'vip_page_vip-purge-cache' );

		$this->assertTrue( wp_script_is( 'vip-cache-manager-dashboard-widget', 'enqueued' ), 'Dashboard widget script should be enqueued.' );
		$this->assertTrue( wp_style_is( 'wp-components', 'enqueued' ), 'WordPress component styles should be enqueued for modal rendering.' );
		$this->assertTrue( wp_style_is( 'vip-cache-manager-dashboard-widget-style', 'enqueued' ), 'Dashboard widget modal styles should be enqueued.' );

		global $wp_scripts;
		$localized_data = $wp_scripts->get_data( 'vip-cache-manager-dashboard-widget', 'data' );

		$this->assertIsString( $localized_data, 'Dashboard widget localized data should be available.' );
		$this->assertSame( 1, preg_match( '/var VIPCacheManagerDashboard = (\{.*\});/s', $localized_data, $matches ), 'Dashboard localized data should be present.' );

		$dashboard_data = json_decode( $matches[1], true );
		$this->assertIsArray( $dashboard_data, 'Dashboard localized data should decode to an array.' );
		$this->assertSame( home_url( '/' ), $dashboard_data['siteUrl'], 'Dashboard localized data should include the current site URL for confirmation matching.' );
		$this->assertArrayHasKey( 'confirmationMismatchMessage', $dashboard_data, 'Dashboard localized data should include mismatch copy for the confirmation guardrail.' );
	}

	private function reset_cache_manager_state(): void {
		$properties = [
			'ban_urls'          => array(),
			'purge_urls'        => array(),
			'site_cache_purged' => false,
		];

		foreach ( $properties as $property => $value ) {
			get_class_property_as_public( WPCOM_VIP_Cache_Manager::class, $property )->setValue( $this->cache_manager, $value );
		}
	}
}
