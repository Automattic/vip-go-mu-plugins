<?php
/**
 * Class SampleTest
 *
 * @package WordPress
 */

declare(strict_types=1);

namespace Parsely\Tests\Integration;

use Parsely\Parsely;
use WP_Scripts;

/**
 * Catch-all class for testing.
 */
final class OtherTest extends TestCase {
	/**
	 * Internal variables
	 *
	 * @var string $parsely Holds the Parsely object.
	 */
	private static $parsely;

	/**
	 * The setUp run before each test
	 */
	public function set_up(): void {
		global $wp_scripts;

		parent::set_up();

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_scripts    = new WP_Scripts();
		self::$parsely = new Parsely();

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
	public function test_version_constant_is_a_semantic_version_string(): void {
		self::assertMatchesRegularExpression(
			'/^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/',
			Parsely::VERSION
		);
	}

	/**
	 * Test cache buster string.
	 *
	 * During tests, this should only return the version constant.
	 *
	 * @covers \Parsely\Parsely::get_asset_cache_buster
	 * @uses \Parsely\Parsely::get_options
	 */
	public function test_cache_buster(): void {
		self::assertSame( Parsely::VERSION, Parsely::get_asset_cache_buster() );
	}

	/**
	 * Check out page filtering.
	 *
	 * @covers \Parsely\Parsely::construct_parsely_metadata
	 * @uses \Parsely\Parsely::get_author_name
	 * @uses \Parsely\Parsely::get_author_names
	 * @uses \Parsely\Parsely::get_bottom_level_term
	 * @uses \Parsely\Parsely::get_category_name
	 * @uses \Parsely\Parsely::get_clean_parsely_page_value
	 * @uses \Parsely\Parsely::get_coauthor_names
	 * @uses \Parsely\Parsely::get_current_url
	 * @uses \Parsely\Parsely::get_first_image
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::get_tags
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 * @group metadata
	 * @group filters
	 */
	public function test_parsely_page_filter(): void {
		// Setup Parsely object.
		$parsely         = new Parsely();
		$parsely_options = get_option( Parsely::OPTIONS_KEY );

		// Create a single post.
		$post_id = $this->factory->post->create();
		$post    = get_post( $post_id );

		// Apply page filtering.
		$headline = 'Completely New And Original Filtered Headline';
		add_filter(
			'wp_parsely_metadata',
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
	 * Test the wp_parsely_post_type filter
	 *
	 * @covers \Parsely\Parsely::construct_parsely_metadata
	 * @uses \Parsely\Parsely::get_options
	 * @uses \Parsely\Parsely::get_author_name
	 * @uses \Parsely\Parsely::get_author_names
	 * @uses \Parsely\Parsely::get_bottom_level_term
	 * @uses \Parsely\Parsely::get_category_name
	 * @uses \Parsely\Parsely::get_clean_parsely_page_value
	 * @uses \Parsely\Parsely::get_coauthor_names
	 * @uses \Parsely\Parsely::get_current_url
	 * @uses \Parsely\Parsely::get_first_image
	 * @uses \Parsely\Parsely::get_tags
	 * @uses \Parsely\Parsely::post_has_trackable_status
	 * @uses \Parsely\Parsely::update_metadata_endpoint
	 */
	public function test_filter_wp_parsely_post_type(): void {
		$options = get_option( Parsely::OPTIONS_KEY );

		$post_id  = $this->go_to_new_post();
		$post_obj = get_post( $post_id );

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
	 * Check that utility methods for checking if the API key is set work correctly.
	 *
	 * @since 2.6.0
	 *
	 * @covers \Parsely\Parsely::api_key_is_set
	 * @covers \Parsely\Parsely::api_key_is_missing
	 * @uses \Parsely\Parsely::get_options
	 */
	public function test_checking_API_key_is_set_or_not(): void {
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
	 * @covers \Parsely\Parsely::get_api_key
	 * @uses \Parsely\Parsely::api_key_is_set
	 * @uses \Parsely\Parsely::get_options
	 */
	public function test_can_retrieve_API_key(): void {
		self::set_options( array( 'apikey' => 'somekey' ) );
		self::assertSame( 'somekey', self::$parsely->get_api_key() );
		self::set_options( array( 'apikey' => '' ) );
		self::assertSame( '', self::$parsely->get_api_key() );
	}

	/**
	 * Test if the `get_options` method can handle a corrupted (not an array) value in the database.
	 *
	 * @since 3.0.0
	 *
	 * @covers \Parsely\Parsely::get_options
	 */
	public function test_corrupted_options(): void {
		update_option( Parsely::OPTIONS_KEY, 'someinvalidvalue' );

		$options = self::$parsely->get_options();
		self::assertSame( self::EMPTY_DEFAULT_OPTIONS, $options );
	}

	/**
	 * Test if post is trackable when it is password protected.
	 *
	 * @since 3.0.1
	 *
	 * @covers \Parsely\Parsely::post_has_trackable_status
	 */
	public function test_post_has_trackable_status_password_protected(): void {
		$post_id = $this->factory->post->create();
		$post    = get_post( $post_id );

		$post->post_password = 'somepassword';

		$result = Parsely::post_has_trackable_status( $post );
		self::assertFalse( $result );
	}

	/**
	 * Test if post is trackable when it is password protected and a filter disables it.
	 *
	 * @since 3.0.1
	 *
	 * @covers \Parsely\Parsely::post_has_trackable_status
	 */
	public function test_post_has_trackable_status_password_protected_with_filter(): void {
		add_filter( 'wp_parsely_skip_post_password_check', '__return_true' );

		$post_id = $this->factory->post->create();
		$post    = get_post( $post_id );

		$post->post_password = 'somepassword';

		$result = Parsely::post_has_trackable_status( $post );
		self::assertTrue( $result );
	}
}
