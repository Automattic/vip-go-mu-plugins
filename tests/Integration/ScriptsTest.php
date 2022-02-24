<?php
/**
 * Parsely Scripts tests.
 *
 * @package Parsely\Tests
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;
use Parsely\Scripts;
use PHPUnit\Framework\RiskyTestError;
use WP_Scripts;

use const Parsely\PARSELY_FILE;

/**
 * Parsely Scripts tests.
 */
final class ScriptsTest extends TestCase {
	/**
	 * Internal variable.
	 *
	 * @var Scripts $scripts Holds the Scripts object
	 */
	private static $scripts;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		global $wp_scripts;

		parent::set_up();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_scripts    = new WP_Scripts();
		self::$scripts = new Scripts( new Parsely() );

		// Set the default options prior to each test.
		TestCase::set_options();
	}

	/**
	 * Test whether the run method adds the register and enqueue actions.
	 *
	 * @covers \Parsely\Scripts::run
	 *
	 * @group scripts
	 */
	public function test_run_adds_actions(): void {
		self::assertFalse( has_action( 'init', array( self::$scripts, 'register_scripts' ) ) );
		self::assertFalse( has_action( 'wp_enqueue_scripts', array( self::$scripts, 'enqueue_js_tracker' ) ) );

		self::$scripts->run();

		self::assertSame( 10, has_action( 'init', array( self::$scripts, 'register_scripts' ) ) );
		self::assertSame( 10, has_action( 'wp_enqueue_scripts', array( self::$scripts, 'enqueue_js_tracker' ) ) );
	}

	/**
	 * Test whether the run method adds the register and enqueue actions when no API key is set.
	 *
	 * @covers \Parsely\Scripts::run
	 *
	 * @group scripts
	 */
	public function test_run_not_adds_actions_no_api_key(): void {
		TestCase::set_options( array( 'apikey' => null ) );

		self::$scripts->run();

		self::assertFalse( has_action( 'init', array( self::$scripts, 'register_scripts' ) ) );
		self::assertFalse( has_action( 'wp_enqueue_scripts', array( self::$scripts, 'enqueue_js_tracker' ) ) );
	}

	/**
	 * Test whether the run method adds the register and enqueue actions when the disable javascript option is set.
	 *
	 * @covers \Parsely\Scripts::run
	 *
	 * @group scripts
	 */
	public function test_run_not_adds_actions_disable_javascript(): void {
		TestCase::set_options( array( 'disable_javascript' => true ) );

		self::$scripts->run();

		self::assertFalse( has_action( 'init', array( self::$scripts, 'register_scripts' ) ) );
		self::assertFalse( has_action( 'wp_enqueue_scripts', array( self::$scripts, 'enqueue_js_tracker' ) ) );
	}

	/**
	 * Test script registration functionality.
	 *
	 * @covers \Parsely\Scripts::register_scripts
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group scripts
	 */
	public function test_parsely_register_scripts(): void {

		// Confirm that API and tracker scripts are not registered.
		$this->assert_script_statuses(
			'wp-parsely-loader',
			array(),
			array( 'registered' )
		);
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array(),
			array( 'registered' )
		);

		// Attempt to register API and tracker scripts.
		self::$scripts->register_scripts();

		// Confirm that API and tracker scripts are now registered
		// (but not yet enqueued).
		$this->assert_script_statuses(
			'wp-parsely-loader',
			array( 'registered' ),
			array( 'enqueued' )
		);
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'registered' ),
			array( 'enqueued' )
		);
	}

	/**
	 * Test the tracker script enqueue.
	 *
	 * @covers \Parsely\Scripts::enqueue_js_tracker
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @uses \Parsely\Scripts::register_scripts
	 * @uses \Parsely\Scripts::script_loader_tag
	 * @group scripts
	 */
	public function test_enqueue_js_tracker(): void {
		global $wp_scripts;

		$this->go_to_new_post();
		self::$scripts->register_scripts();
		self::$scripts->enqueue_js_tracker();

		// Confirm that tracker script is registered and enqueued.
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'registered', 'enqueued' )
		);

		// Confirm that loader script is registered and enqueued.
		$this->assert_script_statuses(
			'wp-parsely-loader',
			array( 'registered', 'enqueued' )
		);

		// Since no secret is provided, the extra fields (inline scripts) on the loader should not be populated.
		self::assertEquals( 1, count( $wp_scripts->registered['wp-parsely-loader']->extra ) );
	}

	/**
	 * Test the wp_parsely_load_js_tracker filter
	 * When it returns false, the tracking script should not be enqueued.
	 *
	 * @covers \Parsely\Scripts::enqueue_js_tracker
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 */
	public function test_wp_parsely_load_js_tracker_filter(): void {
		global $wp_scripts;

		add_filter( 'wp_parsely_load_js_tracker', '__return_false' );

		$this->go_to_new_post();
		self::$scripts->register_scripts();
		self::$scripts->enqueue_js_tracker();

		// Since wp_parsely_load_js_tracker is set to false, enqueue should fail.
		// Confirm that tracker script is registered but not enqueued.
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'registered' ),
			array( 'enqueued' )
		);

		// Since no secret is provided, enqueue should fail.
		// Confirm that API script is registered but not enqueued.
		$this->assert_script_statuses(
			'wp-parsely-loader',
			array( 'registered' ),
			array( 'enqueued' )
		);

		// Since no secret is provided, the extra fields (inline scripts) on the loader should not be populated.
		self::assertEquals( 1, count( $wp_scripts->registered['wp-parsely-loader']->extra ) );
	}

	/**
	 * Test the API init script enqueue.
	 *
	 * @covers \Parsely\Scripts::enqueue_js_tracker
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @uses \Parsely\Scripts::register_scripts
	 * @uses \Parsely\Scripts::script_loader_tag
	 * @group scripts
	 */
	public function test_enqueue_js_api_with_secret(): void {
		global $wp_scripts;

		$this->go_to_new_post();
		self::$scripts->register_scripts();
		self::set_options( array( 'api_secret' => 'hunter2' ) );
		self::$scripts->enqueue_js_tracker();

		// Confirm that API script is registered and enqueued.
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'registered', 'enqueued' )
		);

		// The variable should be inlined before the script.
		self::assertEquals( "window.wpParselyApiKey = 'blog.parsely.com';", $wp_scripts->registered['wp-parsely-loader']->extra['before'][1] );
	}

	/**
	 * Make sure that disabling authenticated user tracking works.
	 *
	 * @covers \Parsely\Scripts::enqueue_js_tracker
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::parsely_is_user_logged_in
	 * @group scripts
	 * @group settings
	 */
	public function test_do_not_track_logged_in_users(): void {
		TestCase::set_options(
			array(
				'api_secret'                => 'hunter2',
				'track_authenticated_users' => false,
			)
		);
		$new_user = $this->create_test_user( 'bill_brasky' );
		wp_set_current_user( $new_user );

		self::$scripts->register_scripts();
		self::$scripts->enqueue_js_tracker();

		// As track_authenticated_users options is false, enqueue should fail.
		// Confirm that tracker script is registered but not enqueued.
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'registered' ),
			array( 'enqueued' )
		);

		// Confirm that API script is registered but not enqueued.
		$this->assert_script_statuses(
			'wp-parsely-loader',
			array( 'registered' ),
			array( 'enqueued' )
		);
	}

	/**
	 * Make sure that disabling authenticated user tracking works in a multisite
	 * environment. The test simulates authenticated and unauthenticated user
	 * activity.
	 *
	 * @covers \Parsely\Scripts::enqueue_js_tracker
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::parsely_is_user_logged_in
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @uses \Parsely\Scripts::register_scripts
	 * @uses \Parsely\Scripts::script_loader_tag
	 * @group scripts
	 * @group settings
	 */
	public function test_do_not_track_logged_in_users_multisite(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( "this test can't run without multisite" );
		}

		// Set up users and blogs.
		$first_blog_admin  = $this->create_test_user( 'optimus_prime' );
		$second_blog_admin = $this->create_test_user( 'megatron' );
		$first_blog        = $this->create_test_blog( 'autobots', $first_blog_admin );
		$second_blog       = $this->create_test_blog( 'decepticons', $second_blog_admin );

		// These custom options will be used for both blogs.
		$custom_options = array(
			'track_authenticated_users' => false, // Don't track logged-in users.
			'apikey'                    => 'blog.parsely.com',
		);

		// Only first admin is logged-in throughout the test.
		wp_set_current_user( $first_blog_admin );

		// -- Test first blog.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		switch_to_blog( $first_blog );
		TestCase::set_options( $custom_options );
		$this->go_to_new_post();

		// Check that we're on the first blog and that first user is a member.
		self::assertEquals( get_current_blog_id(), $first_blog );
		self::assertTrue( is_user_member_of_blog( $first_blog_admin, $first_blog ) );

		// Enqueue tracker script.
		self::$scripts->register_scripts();
		self::$scripts->enqueue_js_tracker();

		// Current user is logged-in and track_authenticated_users is false so enqueue
		// should fail. Confirm that tracker script is registered but not enqueued.
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'registered' ),
			array( 'enqueued' )
		);

		// -- Test second blog.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		switch_to_blog( $second_blog );
		TestCase::set_options( $custom_options );
		$this->go_to_new_post();

		// Check that we're on the second blog and that first user is not a member.
		self::assertEquals( get_current_blog_id(), $second_blog );
		self::assertFalse( is_user_member_of_blog( $first_blog_admin, get_current_blog_id() ) );

		// Enqueue tracker script.
		self::$scripts->register_scripts();
		self::$scripts->enqueue_js_tracker();

		// First user is not logged-in to the second blog, so track_authenticated_users value
		// is irrelevant. Confirm that tracker script is registered and enqueued.
		$this->assert_script_statuses(
			'wp-parsely-tracker',
			array( 'enqueued', 'registered' )
		);
	}

	/**
	 * Test that the tracker script is correctly output in HTML markup
	 * when the wp_parsely_enable_cfasync_attribute filter is used.
	 *
	 * @covers \Parsely\Scripts::enqueue_js_tracker
	 * @uses \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @uses \Parsely\Scripts::register_scripts
	 * @uses \Parsely\Scripts::script_loader_tag
	 * @group scripts
	 * @group scripts-output
	 */
	public function test_tracker_markup_has_attribute_when_cfasync_filter_is_used(): void {
		add_filter( 'wp_parsely_enable_cfasync_attribute', '__return_true' );

		ob_start();
		$this->go_to_new_post();
		self::$scripts->register_scripts();
		self::$scripts->enqueue_js_tracker();

		wp_print_scripts();
		$output = ob_get_clean();

		$loader_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/loader.asset.php';

		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		self::assertStringContainsString( "<script data-cfasync=\"false\" type='text/javascript' src='http://example.org/wp-content/plugins/wp-parsely/tests/Integration/../../build/loader.js?ver=" . $loader_asset['version'] . "' id='wp-parsely-loader-js'></script>", $output );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		self::assertStringContainsString( "<script data-cfasync=\"false\" type='text/javascript' data-parsely-site=\"blog.parsely.com\" src='https://cdn.parsely.com/keys/blog.parsely.com/p.js?ver=123456.78.9' id=\"parsely-cfg\"></script>", $output );
	}

	/**
	 * Assert multiple enqueueing statuses for a script.
	 *
	 * @param string $handle       Script handle to test.
	 * @param array  $assert_true  Optional. Statuses that should assert to true. Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'. Default is an empty array.
	 * @param array  $assert_false Optional. Statuses that should assert to false. Accepts 'enqueued', 'registered', 'queue', 'to_do', and 'done'. Default is an empty array.
	 *
	 * @throws RiskyTestError If no assertions ($assert_true, $assert_false) get passed to the function.
	 */
	public function assert_script_statuses( string $handle, array $assert_true = array(), array $assert_false = array() ): void {
		if ( 0 === count( $assert_true ) + count( $assert_false ) ) {
			throw new RiskyTestError( 'Function assert_script_statuses() has been used without any arguments' );
		}

		foreach ( $assert_true as $status ) {
			self::assertTrue(
				wp_script_is( $handle, $status ),
				"Unexpected script status: $handle status should be '$status'"
			);
		}

		foreach ( $assert_false as $status ) {
			self::assertFalse(
				wp_script_is( $handle, $status ),
				"Unexpected script status: $handle status should NOT be '$status'"
			);
		}
	}
}
