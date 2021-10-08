<?php
/**
 * Class SampleTest
 *
 * @package WordPress
 */

namespace Parsely\Tests\Integration;

/**
 * Catch-all class for testing.
 * TODO: Break this into multiple targeted files.
 */
final class OtherTest extends TestCase {
	/**
	 * Internal variables
	 *
	 * @var string $parsely Holds the Parsely object.
	 */
	protected static $parsely;

	/**
	 * The setUp run before each test
	 */
	public function set_up() {
		global $wp_scripts;

		parent::set_up();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_scripts    = new \WP_Scripts();
		self::$parsely = new \Parsely();

		// Set the default options prior to each test.
		TestCase::set_options();
	}

	/**
	 * Make sure the version is semver-compliant
	 *
	 * @see https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
	 * @see https://regex101.com/r/Ly7O1x/3/
	 *
	 * @coversNothing
	 */
	public function test_version_constant_is_a_semantic_version_string() {
		self::assertMatchesRegularExpression(
			'/^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
			\Parsely::VERSION
		);
	}

	/**
	 * Test cache buster string.
	 *
	 * During tests, this should only return the version constant.
	 *
	 * @covers \Parsely::get_asset_cache_buster
	 * @uses \Parsely::get_options
	 */
	public function test_cache_buster() {
		self::assertSame( \Parsely::VERSION, \Parsely::get_asset_cache_buster() );
	}

	/**
	 * Test JavaScript registrations.
	 *
	 * @covers \Parsely::register_js
	 * @uses \Parsely::get_asset_cache_buster
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_options
	 * @uses \Parsely::update_metadata_endpoint
	 * @group insert-js
	 */
	public function test_parsely_register_js() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm nothing was printed by register_js()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-api', 'registered' ),
			'Failed to confirm API script was registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm API script was not enqueued'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-tracker', 'registered' ),
			'Failed to confirm API script was registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm API script was not enqueued'
		);
	}

	/**
	 * Test the tracker script enqueue.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @uses \Parsely::get_asset_cache_buster
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::register_js
	 * @uses \Parsely::script_loader_tag
	 * @uses \Parsely::update_metadata_endpoint
	 * @group insert-js
	 */
	public function test_load_js_tracker() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();
		self::$parsely->load_js_tracker();
		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			"<script data-cfasync=\"false\" type='text/javascript' data-parsely-site=\"blog.parsely.com\" src='https://cdn.parsely.com/keys/blog.parsely.com/p.js?ver=" . \Parsely::VERSION . "' id=\"parsely-cfg\"></script>\n",
			$output,
			'Failed to confirm script tag was printed correctly'
		);
	}

	/**
	 * Test the API init script enqueue.
	 *
	 * @covers \Parsely::load_js_api
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_asset_cache_buster
	 * @uses \Parsely::get_options
	 * @uses \Parsely::register_js
	 * @uses \Parsely::update_metadata_endpoint
	 * @group insert-js
	 */
	public function test_load_js_api_no_secret() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();
		self::$parsely->load_js_api();
		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_api()'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm api script was not enqueued when an API secret is not set'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm script was not printed'
		);
	}

	/**
	 * Test the API init script enqueue.
	 *
	 * @covers \Parsely::load_js_api
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_asset_cache_buster
	 * @uses \Parsely::get_options
	 * @uses \Parsely::register_js
	 * @uses \Parsely::script_loader_tag
	 * @uses \Parsely::update_metadata_endpoint
	 * @group insert-js
	 */
	public function test_load_js_api_with_secret() {
		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->register_js();

		self::set_options( array( 'api_secret' => 'hunter2' ) );

		self::$parsely->load_js_api();
		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_api()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm api script was enqueued when an API secret is set'
		);

		wp_print_scripts();
		$output = ob_get_clean();


		self::assertStringContainsString(
			"<script type='text/javascript' id='wp-parsely-api-js-extra'>
/* <![CDATA[ */
var wpParsely = {\"apikey\":\"blog.parsely.com\"};
/* ]]> */
</script>",
			$output,
			'Failed to confirm "localized" data were embedded'
		);

		self::assertStringContainsString(
			"<script data-cfasync=\"false\" type='text/javascript' src='" . esc_url( plugin_dir_url( PARSELY_FILE ) ) . 'build/init-api.js?ver=' . \Parsely::VERSION . "' id='wp-parsely-api-js'></script>",
			$output,
			'Failed to confirm script tag was printed correctly'
		);
	}

	/**
	 * Check out page filtering.
	 *
	 * @expectedDeprecated after_set_parsely_page
	 * @covers \Parsely::construct_parsely_metadata
	 * @uses \Parsely::get_author_name
	 * @uses \Parsely::get_author_names
	 * @uses \Parsely::get_bottom_level_term
	 * @uses \Parsely::get_category_name
	 * @uses \Parsely::get_clean_parsely_page_value
	 * @uses \Parsely::get_coauthor_names
	 * @uses \Parsely::get_current_url
	 * @uses \Parsely::get_first_image
	 * @uses \Parsely::get_options
	 * @uses \Parsely::get_tags
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group filters
	 */
	public function test_parsely_page_filter() {
		// Setup Parsley object.
		$parsely         = new \Parsely();
		$parsely_options = get_option( \Parsely::OPTIONS_KEY );

		// Create a single post.
		$post_id = $this->factory->post->create();
		$post    = get_post( $post_id );

		// Apply page filtering.
		$headline = 'Completely New And Original Filtered Headline';
		add_filter(
			'after_set_parsely_page',
			function( $args ) use ( $headline ) {
				$args['headline'] = $headline;

				return $args;
			},
			10,
			3
		);

		// Create the structured data for that post.
		$structured_data = $parsely->construct_parsely_metadata( $parsely_options, $post );

		// The structured data should contain the headline from the filter.
		self::assertSame( strpos( $structured_data['headline'], $headline ), 0 );
	}

	/**
	 * Make sure users can log in.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_options
	 * @uses \Parsely::parsely_is_user_logged_in
	 * @group insert-js
	 * @group settings
	 */
	public function test_user_logged_in() {
		TestCase::set_options( array( 'track_authenticated_users' => false ) );
		$new_user = $this->create_test_user( 'bill_brasky' );
		wp_set_current_user( $new_user );

		ob_start();
		self::$parsely->load_js_tracker();

		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'registered' ),
			'Failed to confirm API script was not registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-api', 'enqueued' ),
			'Failed to confirm API script was not enqueued'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'registered' ),
			'Failed to confirm tracker script was not registered'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was not enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm script tags were not printed'
		);
	}

	/**
	 * Make sure users can log in to more than one site.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_asset_cache_buster
	 * @uses \Parsely::get_options
	 * @uses \Parsely::parsely_is_user_logged_in
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::register_js
	 * @uses \Parsely::script_loader_tag
	 * @uses \Parsely::update_metadata_endpoint
	 * @group insert-js
	 * @group settings
	 */
	public function test_user_logged_in_multisite() {
		if ( ! is_multisite() ) {
			self::markTestSkipped( "this test can't run without multisite" );
		}

		$new_user    = $this->create_test_user( 'optimus_prime' );
		$second_user = $this->create_test_user( 'megatron' );
		$first_blog  = $this->create_test_blog( 'autobots', $new_user );
		$second_blog = $this->create_test_blog( 'decepticons', $second_user );

		wp_set_current_user( $new_user );
		switch_to_blog( $first_blog );

		// These custom options will be used for both blog_ids.
		$custom_options = array(
			'track_authenticated_users' => false,
			'apikey'                    => 'blog.parsely.com',
		);
		TestCase::set_options( $custom_options );

		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );

		self::assertEquals( get_current_blog_id(), $first_blog );
		self::assertTrue( is_user_member_of_blog( $new_user, $first_blog ) );
		self::assertFalse( is_user_member_of_blog( $new_user, $second_blog ) );

		ob_start();
		self::$parsely->register_js();
		self::$parsely->load_js_tracker();

		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertFalse(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was not enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			'',
			$output,
			'Failed to confirm script tags were not printed'
		);

		switch_to_blog( $second_blog );
		TestCase::set_options( $custom_options );

		self::assertEquals( get_current_blog_id(), $second_blog );
		self::assertFalse( is_user_member_of_blog( $new_user, get_current_blog_id() ) );

		ob_start();
		self::$parsely->register_js();
		self::$parsely->load_js_tracker();

		$intermediate_output = ob_get_contents();
		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		self::assertTrue(
			wp_script_is( 'wp-parsely-tracker', 'enqueued' ),
			'Failed to confirm tracker script was enqueued'
		);

		wp_print_scripts();
		$output = ob_get_clean();

		self::assertSame(
			"<script data-cfasync=\"false\" type='text/javascript' data-parsely-site=\"blog.parsely.com\" src='https://cdn.parsely.com/keys/blog.parsely.com/p.js?ver=" . \Parsely::VERSION . "' id=\"parsely-cfg\"></script>\n",
			$output,
			'Failed to confirm script tags were printed correctly'
		);
	}

	/**
	 * Test the wp_parsely_load_js_tracker filter
	 * When it returns false, the tracking script should not be enqueued.
	 *
	 * @covers \Parsely::load_js_tracker
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 */
	public function test_load_js_tracker_filter() {
		add_filter( 'wp_parsely_load_js_tracker', '__return_false' );

		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->load_js_tracker();
		$intermediate_output = ob_get_contents();

		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		wp_print_scripts();

		$output = ob_get_clean();
		self::assertSame(
			'',
			$output,
			'Failed to confirm filter prevented enqueued scripts'
		);
	}

	/**
	 * Test the parsely_filter_insert_javascript filter
	 * When it returns false, the tracking script should not be enqueued.
	 *
	 * @deprecated deprecated since 2.5.0. This test can be removed when the filter is removed.
	 *
	 * @expectedDeprecated parsely_filter_insert_javascript
	 *
	 * @covers \Parsely::load_js_tracker
	 * @uses \Parsely::api_key_is_missing
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_options
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 */
	public function test_deprecated_insert_javascript_filter() {
		add_filter( 'parsely_filter_insert_javascript', '__return_false' );

		ob_start();
		$post_array = $this->create_test_post_array();
		$post       = $this->factory->post->create( $post_array );
		$this->go_to( '/?p=' . $post );
		self::$parsely->load_js_tracker();
		$intermediate_output = ob_get_contents();

		self::assertSame(
			'',
			$intermediate_output,
			'Failed to confirm scripts were not printed by load_js_tracker()'
		);

		wp_print_scripts();

		$output = ob_get_clean();
		self::assertSame(
			'',
			$output,
			'Failed to confirm filter prevented enqueued scripts'
		);
	}

	/**
	 * Test the wp_parsely_post_type filter
	 *
	 * @covers \Parsely::construct_parsely_metadata
	 * @uses \Parsely::get_options
	 * @uses \Parsely::get_author_name
	 * @uses \Parsely::get_author_names
	 * @uses \Parsely::get_bottom_level_term
	 * @uses \Parsely::get_category_name
	 * @uses \Parsely::get_clean_parsely_page_value
	 * @uses \Parsely::get_coauthor_names
	 * @uses \Parsely::get_current_url
	 * @uses \Parsely::get_first_image
	 * @uses \Parsely::get_tags
	 * @uses \Parsely::post_has_trackable_status
	 * @uses \Parsely::update_metadata_endpoint
	 */
	public function test_filter_wp_parsely_post_type() {
		$options = get_option( \Parsely::OPTIONS_KEY );

		$post_array = $this->create_test_post_array();
		$post_id    = $this->factory->post->create( $post_array );
		$post_obj   = get_post( $post_id );
		$this->go_to( '/?p=' . $post_id );

		// Try to change the post type to a supported value - BlogPosting.
		add_filter(
			'wp_parsely_post_type',
			function() {
				return 'BlogPosting';
			}
		);

		$metadata = self::$parsely->construct_parsely_metadata( $options, $post_obj );
		self::assertSame( 'BlogPosting', $metadata['@type'] );

		// Try to change the post type to a non-supported value - Not_Supported.
		add_filter(
			'wp_parsely_post_type',
			function() {
				return 'Not_Supported_Type';
			}
		);

		$this->expectWarning();
		$this->expectWarningMessage( '@type Not_Supported_Type is not supported by Parse.ly. Please use a type mentioned in https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages' );
		self::$parsely->construct_parsely_metadata( $options, $post_obj );
	}

	/**
	 * Test that test_display_admin_warning action returns a warning when there is no key
	 *
	 * @covers \Parsely::should_display_admin_warning
	 * @uses \Parsely::get_options
	 */
	public function test_display_admin_warning_without_key() {
		$should_display_admin_warning = self::getMethod( 'should_display_admin_warning' );
		$this->set_options( array( 'apikey' => '' ) );

		$response = $should_display_admin_warning->invoke( self::$parsely );
		self::assertTrue( $response );
	}

	/**
	 * Test that test_display_admin_warning action returns a warning when there is no key
	 *
	 * @covers \Parsely::should_display_admin_warning
	 */
	public function test_display_admin_warning_network_admin() {
		$should_display_admin_warning = self::getMethod( 'should_display_admin_warning' );
		$this->set_options( array( 'apikey' => '' ) );
		set_current_screen( 'dashboard-network' );

		$response = $should_display_admin_warning->invoke( self::$parsely );
		self::assertFalse( $response );
	}

	/**
	 * Test that test_display_admin_warning action doesn't return a warning when there is a key
	 *
	 * @covers \Parsely::should_display_admin_warning
	 * @uses \Parsely::get_options
	 */
	public function test_display_admin_warning_with_key() {
		$should_display_admin_warning = self::getMethod( 'should_display_admin_warning' );
		$this->set_options( array( 'apikey' => 'somekey' ) );

		$response = $should_display_admin_warning->invoke( self::$parsely );
		self::assertFalse( $response );
	}

	/**
	 * Check that utility methods for checking if the API key is set work correctly.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely::api_key_is_set
	 * @covers \Parsely::api_key_is_missing
	 * @uses \Parsely::get_options
	 */
	public function test_checking_API_key_is_set_or_not() {
		self::set_options( array( 'apikey' => '' ) );
		self::assertFalse( self::$parsely->api_key_is_set() );
		self::assertTrue( self::$parsely->api_key_is_missing() );

		self::set_options( array( 'apikey' => 'somekey' ) );
		self::assertTrue( self::$parsely->api_key_is_set() );
		self::assertFalse( self::$parsely->api_key_is_missing() );
	}

	/**
	 * Test the utility methods for retrieving the API key.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely::get_api_key
	 * @uses \Parsely::api_key_is_set
	 * @uses \Parsely::get_options
	 */
	public function test_can_retrieve_API_key() {
		self::set_options( array( 'apikey' => 'somekey' ) );
		self::assertSame( 'somekey', self::$parsely->get_api_key() );
		self::set_options( array( 'apikey' => '' ) );
		self::assertSame( '', self::$parsely->get_api_key() );
	}
}
