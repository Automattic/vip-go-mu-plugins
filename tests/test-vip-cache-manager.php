<?php

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

class VIP_Go_Cache_Manager_Test extends WP_UnitTestCase {
	/** @var WPCOM_VIP_Cache_Manager */
	public $cache_manager;

	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->cache_manager = WPCOM_VIP_Cache_Manager::instance();
		$this->cache_manager->init();
		$this->cache_manager->clear_queued_purge_urls();
		$this->reset_cache_manager_state();

		$this->original_error_reporting = error_reporting();
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( error_reporting() & $errno ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, $errno );
			}

			return false;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		$this->reset_cache_manager_state();
		restore_error_handler();
		error_reporting( $this->original_error_reporting );
		parent::tearDown();
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
	public function test__invalid__queue_purge_url__warning( $queue_url ) {
		$this->expectException( ErrorException::class );
		$this->expectExceptionCode( E_USER_WARNING );
		$this->cache_manager->queue_purge_url( $queue_url );
	}

	/**
	 * @dataProvider get_data_for_invalid_queue_purge_url_test
	 */
	public function test__invalid__queue_purge_url( $queue_url ) {
		error_reporting( $this->original_error_reporting & ~E_USER_WARNING );

		$result = $this->cache_manager->queue_purge_url( $queue_url );
		self::assertFalse( $result );
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

	public function test_current_user_can_purge_cache_filter_receives_scope_and_user() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$captured_scope = null;
		$captured_user  = null;

		$callback = static function ( $can_purge_cache, $user, $scope ) use ( &$captured_scope, &$captured_user ) {
			$captured_scope = $scope;
			$captured_user  = $user;

			return $can_purge_cache;
		};

		add_filter( 'vip_cache_manager_can_purge_cache', $callback, 10, 3 );

		try {
			$method = new \ReflectionMethod( WPCOM_VIP_Cache_Manager::class, 'current_user_can_purge_cache' );
			$method->invoke( $this->cache_manager, 'url' );
		} finally {
			remove_filter( 'vip_cache_manager_can_purge_cache', $callback, 10 );
		}

		$this->assertSame( 'url', $captured_scope, 'Scope should be passed to the permission filter.' );
		$this->assertInstanceOf( WP_User::class, $captured_user, 'Current user should be passed to the permission filter.' );
		$this->assertSame( $user_id, $captured_user->ID, 'Permission filter should receive the current user ID.' );
	}

	public function test_current_user_can_purge_cache_filter_scope_defaults_to_null() {
		$captured_scope = 'not-set';

		$callback = static function ( $can_purge_cache, $user, $scope ) use ( &$captured_scope ) {
			$captured_scope = $scope;

			return $can_purge_cache;
		};

		add_filter( 'vip_cache_manager_can_purge_cache', $callback, 10, 3 );

		try {
			$method = new \ReflectionMethod( WPCOM_VIP_Cache_Manager::class, 'current_user_can_purge_cache' );
			$method->invoke( $this->cache_manager );
		} finally {
			remove_filter( 'vip_cache_manager_can_purge_cache', $callback, 10 );
		}

		$this->assertNull( $captured_scope, 'Scope should default to null when no specific purge context is provided.' );
	}

	public function test_current_user_can_purge_cache_allows_scope_specific_permissions() {
		$callback = static function ( $can_purge_cache, $user, $scope ) {
			return 'url' === $scope;
		};

		add_filter( 'vip_cache_manager_can_purge_cache', $callback, 10, 3 );

		try {
			$method = new \ReflectionMethod( WPCOM_VIP_Cache_Manager::class, 'current_user_can_purge_cache' );
			$this->assertTrue( $method->invoke( $this->cache_manager, 'url' ), 'URL scope should be allowed by the filter callback.' );
			$this->assertFalse( $method->invoke( $this->cache_manager, 'site' ), 'Non-URL scope should be denied by the filter callback.' );
		} finally {
			remove_filter( 'vip_cache_manager_can_purge_cache', $callback, 10 );
		}
	}

	public function test_available_manual_purge_actions_are_filtered_by_scope_permissions() {
		$callback = static function ( $can_purge_cache, $user, $scope ) {
			return in_array( $scope, [ 'url', 'origin' ], true );
		};

		add_filter( 'vip_cache_manager_can_purge_cache', $callback, 10, 3 );

		try {
			$method          = new \ReflectionMethod( WPCOM_VIP_Cache_Manager::class, 'get_available_manual_purge_actions_config' );
			$visible_actions = $method->invoke( $this->cache_manager );
		} finally {
			remove_filter( 'vip_cache_manager_can_purge_cache', $callback, 10 );
		}

		$this->assertSame( [ 'url', 'origin' ], array_keys( $visible_actions ), 'Only allowed purge scopes should be returned for rendering.' );
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

	private function reset_cache_manager_state(): void {
		$properties = [
			'ban_urls'          => array(),
			'purge_urls'        => array(),
			'site_cache_purged' => false,
		];

		foreach ( $properties as $property => $value ) {
			$reflection = new \ReflectionProperty( WPCOM_VIP_Cache_Manager::class, $property );
			$reflection->setValue( $this->cache_manager, $value );
		}
	}
}
